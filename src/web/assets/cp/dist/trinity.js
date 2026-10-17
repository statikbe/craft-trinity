/**
 * Trinity: the control panel layer for rows.
 *
 * A row stays two native fields: a layout button group (layoutField) and a Matrix of cell entries in cards view
 * (cellsField), one entry per column from left to right. This layer only changes how they are shown and edited:
 *  - the cards are laid out like the columns of the row's layout, each with its width on the page;
 *  - columns without content show a placeholder to add content (types that don't fit that width, or can't be combined with
 *    the other blocks in the row, are listed disabled with the reason; types that fit no column on this page aren't listed), or to merge
 *    it with the column next to it;
 *  - "+" buttons on the left and right add a column (up to 3, or 2 on pages where the builder is 2/3 wide, see contextWidth);
 *  - a card dragged (by its move handle) onto an empty column of another row moves there: Craft can't move a nested
 *    entry to another owner, so it is duplicated into that row (like Copy + Paste) and then deleted here.
 * Empty columns only exist here: saving a row with fewer blocks than columns fails validation (Rows::validateRow()).
 *  - with rowSpacingField set, that Button Group is hidden and its options are shown as a compact "Space above" control at the
 *    top of every row in the content builder but the first; the block's gap follows the choice (close / extra) as a hint. With
 *    rowBackgroundField, the control is disabled on a row whose background differs from the row above.
 * Settings and field handles come from Rows::cpConfig(), in a [data-trinity] element in the cells field.
 * When Craft's CP changes and this breaks, the native fields keep working without it.
 */
(function ($) {
    // Column spans in sixths per layout, from the layouts in the config (Layout::LAYOUTS), e.g. thirdTwoThirds: [2, 4]
    let SPANS = null;
    // Layout after adding a column, by the new number of columns
    const LAYOUT_FOR_COLUMNS = {2: 'halves', 3: 'thirds'};
    // Layout after merging column i with column i + 1, by "<layout>:<i>"
    const MERGED_LAYOUT = {
        'halves:0': 'full',
        'thirdTwoThirds:0': 'full',
        'twoThirdsThird:0': 'full',
        'thirds:0': 'twoThirdsThird',
        'thirds:1': 'thirdTwoThirds',
    };
    const EPSILON = 0.01;

    // Every content row on the page, to find drop targets when a card is dragged out of its row
    const rows = new Set();

    /**
     * Re-renders the form so field conditions that depend on the blocks in a row ("Row cells") apply. Craft only
     * re-checks conditions when the form data changes, and adding or removing a card (a nested entry) doesn't change it;
     * it also doesn't update conditional fields inside blocks then. Debounced: several changes give one refresh.
     * Waits while another slideout is open: re-rendering the form under a new block's slideout breaks its "Cancel".
     */
    const refreshTimers = new Map();
    const refreshConditions = (elementEditor, delay = 500) => {
        if (!elementEditor?.refreshContent) {
            return;
        }
        clearTimeout(refreshTimers.get(elementEditor));
        refreshTimers.set(elementEditor, setTimeout(() => {
            // Another slideout than the one this form may be in itself (a page edited in a slideout)
            const form = elementEditor.$container?.[0];
            if ([...document.querySelectorAll('.slideout-container:not(.hidden)')].some((slideout) => !slideout.contains(form))) {
                refreshConditions(elementEditor, 300);
                return;
            }
            refreshTimers.delete(elementEditor);
            elementEditor.refreshContent().catch((e) => console.warn('Couldn’t refresh the field conditions:', e));
        }, delay));
    };

    /** Width as a readable fraction: 1/3, 1/2, 3/8 … (like Layout::formatWidth()) */
    const formatWidth = (width) => {
        for (const denominator of [1, 2, 3, 4, 6, 8, 9]) {
            const numerator = Math.round(width * denominator);
            if (Math.abs(width * denominator - numerator) < EPSILON) {
                return numerator === denominator ? 'full' : `${numerator}/${denominator}`;
            }
        }
        return `${Math.round(width * 100)}%`;
    };

    /** Width of a column on the page, for editors: "1/2 of the page", "full page width" */
    const formatPageWidth = (width) => {
        const fraction = formatWidth(width);
        return fraction === 'full' ? Craft.t('trinity', 'full page width') : Craft.t('trinity', '{width} of the page', {width: fraction});
    };

    const GridRow = Garnish.Base.extend({
        $row: null,
        $cards: null,
        nem: null,
        listbox: null,
        config: null,
        empties: null,
        slotOfId: null,
        knownIds: null,
        rendered: false,
        pendingSlot: null,
        rendering: false,
        observer: null,
        resizeObserver: null,
        placeholders: null,
        $addLeft: null,
        $addRight: null,
        sort: null,
        dragging: null,
        $errorFields: null,
        blockTypes: null,

        init(row, cardsContainer, nem, listbox, config) {
            this.$row = $(row);
            this.$cards = $(cardsContainer).addClass('trinity');
            this.nem = nem;
            this.listbox = listbox;
            this.config = config;
            this.empties = [];
            this.slotOfId = {};
            this.knownIds = new Set();
            this.placeholders = {};

            this.hideDisallowedLayouts();
            this.initSpacing();

            this.$addLeft = this.addColumnButton('left');
            this.$addRight = this.addColumnButton('right');

            this.listbox.on('change', () => this.render());
            this.observer = new MutationObserver(() => {
                if (!this.rendering) {
                    requestAnimationFrame(() => this.render());
                }
            });
            this.observer.observe(this.$cards[0], {childList: true, subtree: true});
            this.resizeObserver = new ResizeObserver(() => this.positionAddButtons());
            this.resizeObserver.observe(this.$cards[0]);

            rows.add(this);
            this.showSavedErrors();
            this.render();
        },

        // Layout
        // ---------------------------------------------------------------------

        getLayout() {
            const value = this.listbox.$selectedOption?.data('value') ?? '';
            const layout = value.startsWith('base64:') ? atob(value.substring(7)) : value;
            return SPANS[layout] ? layout : 'full';
        },

        setLayout(layout) {
            const index = this.listbox.$options.toArray().findIndex((option) => $(option).data('value') === `base64:${btoa(layout)}`);
            if (index !== -1) {
                this.listbox.select(index);
            }
        },

        isAllowed(layout) {
            return this.config.allowedLayouts.includes(layout);
        },

        // Space above the row
        // ---------------------------------------------------------------------

        /** The rowSpacingField Button Group as a compact control at the top of the row's block (only for rows in a content builder) */
        initSpacing() {
            const handle = this.config.handles.rowSpacingField;
            if (!handle || !this.$row.hasClass('matrixblock')) {
                return;
            }
            const $field = this.$row.find(`[data-attribute="${handle}"]`).first();
            const listbox = $field.find('.btngroup').first().data('listbox');
            if (!listbox || this.$row.children('.trinity-spacing').length) {
                return;
            }

            const $bar = $('<div/>', {
                class: 'trinity-spacing',
                title: Craft.t('trinity', 'Only between rows with the same background'),
            }).prependTo(this.$row);
            $('<span/>', {class: 'trinity-spacing__label', text: Craft.t('trinity', 'Space above')}).appendTo($bar);
            const $group = $('<div/>', {class: 'trinity-spacing__options', role: 'group', 'aria-label': Craft.t('trinity', 'Space above')}).appendTo($bar);
            const buttons = listbox.$options.toArray().map((option, index) => {
                const $button = $('<button/>', {type: 'button', class: 'trinity-spacing__option'})
                    .append($(option).children().clone())
                    .appendTo($group);
                $button.attr('aria-label', $(option).text().trim());
                this.addListener($button, 'click', () => listbox.select(index));
                return $button;
            });
            $field.addClass('hidden');

            const update = () => {
                const selected = listbox.$options.index(listbox.$selectedOption);
                buttons.forEach(($button, index) => $button.toggleClass('active', index === selected).attr('aria-pressed', index === selected ? 'true' : 'false'));
                const value = (listbox.$selectedOption?.data('value') ?? '').toString();
                this.$row.attr('data-trinity-spacing', value.startsWith('base64:') ? atob(value.substring(7)) : value);
            };
            listbox.on('change', update);
            update();
            watchBuilder(this.$row[0].parentElement, this.config);
        },

        /** Layouts that can't be used here are hidden; the selected one stays visible (marked) so it can be changed */
        hideDisallowedLayouts() {
            for (const option of this.listbox.$options.toArray()) {
                const value = $(option).data('value') ?? '';
                const layout = value.startsWith('base64:') ? atob(value.substring(7)) : value;
                const allowed = this.isAllowed(layout);
                const selected = option === this.listbox.$selectedOption?.[0];
                $(option)
                    .toggleClass('hidden', !allowed && !selected)
                    .toggleClass('trinity__layout-invalid', !allowed && selected)
                    .attr('title', !allowed && selected ? Craft.t('trinity', 'This layout has columns that are too narrow for this page.') : null);
            }
            // Rounded corners on the first/last visible button, also when the ones around it are hidden
            const $visible = this.listbox.$options.not('.hidden');
            this.listbox.$options.not($visible.first()).removeClass('btngroup-btn-first');
            this.listbox.$options.not($visible.last()).removeClass('btngroup-btn-last');
            $visible.first().addClass('btngroup-btn-first');
            $visible.last().addClass('btngroup-btn-last');
        },

        /** Width of a column on the page, as a fraction (the builder can be e.g. 2/3 or 3/4 of the page) */
        pageWidth(span) {
            return (span / 6) * this.config.contextWidth;
        },

        typeFits(typeId, width) {
            return !this.tooNarrow(typeId, width) && !this.tooWide(typeId, width);
        },

        /** Whether a type fits a column of any layout allowed on this page (on a 2/3 page, a type that needs the full width doesn't) */
        fitsPage(typeId) {
            return this.config.allowedLayouts.some((layout) => SPANS[layout]?.some((span) => this.typeFits(typeId, this.pageWidth(span))));
        },

        tooNarrow(typeId, width) {
            return width < (this.config.minWidthPerType[typeId] ?? 0) - EPSILON;
        },

        tooWide(typeId, width) {
            return width > (this.config.maxWidthPerType?.[typeId] ?? 1) + EPSILON;
        },

        /** Why a type doesn't fit a column of this width (shown under it in the "Add content" menu), or null when it fits */
        sizeHint(typeId, width) {
            if (this.tooNarrow(typeId, width)) {
                return Craft.t('trinity', 'Min. width {width}', {width: formatWidth(this.config.minWidthPerType[typeId])});
            }
            if (this.tooWide(typeId, width)) {
                return Craft.t('trinity', 'Max. width {width}', {width: formatWidth(this.config.maxWidthPerType[typeId])});
            }
            return null;
        },

        /** Entry type ids of the cards in this row, without the given card element */
        cardTypeIds(except = null) {
            return this.getList().children('li').children('.element').toArray()
                .filter((element) => element !== except)
                .map((element) => $(element).data('entry-type-id'));
        },

        /** The first of otherTypeIds that typeId can't share a row with (contentBuilderGrid.blockedCombinations), or null */
        blockedBy(typeId, otherTypeIds) {
            const blocked = this.config.blockedCombinations?.[typeId] ?? [];
            return otherTypeIds.find((other) => blocked.includes(other)) ?? null;
        },

        typeName(typeId) {
            return (this.nem.settings.createAttributes ?? []).find((attributes) => attributes.attributes.typeId == typeId)?.label ?? '';
        },

        /** Why a type can't be added to an empty column of this width (shown under it in the "Add content" menu), or null */
        addHint(typeId, width) {
            const other = this.blockedBy(typeId, this.cardTypeIds());
            return this.sizeHint(typeId, width)
                ?? (other !== null ? Craft.t('trinity', 'Not with {type}', {type: this.typeName(other)}) : null);
        },

        // Rendering
        // ---------------------------------------------------------------------

        getList() {
            let $list = this.$cards.children('ul.elements');
            if (!$list.length) {
                // A row without cells: create the list the nested element manager will use for its first card
                $list = $('<ul/>', {class: 'elements card-grid'}).prependTo(this.$cards);
            }
            return $list;
        },

        render() {
            this.rendering = true;
            const $list = this.getList();
            const layout = this.getLayout();
            const spans = SPANS[layout];
            const columns = spans.length;
            const $cardItems = $list.children('li').filter((i, li) => $(li).children('.element').length);
            const ids = $cardItems.toArray().map((li) => String($(li).children('.element').data('id')));

            // A new card: put it in the column it was created for
            const newIds = ids.filter((id) => !this.knownIds.has(id));
            if (this.rendered && this.pendingSlot !== null && newIds.length === 1) {
                const slot = this.pendingSlot;
                this.pendingSlot = null;
                this.rendering = false;
                this.placeNewCard($cardItems, newIds[0], slot);
                return;
            }

            // A removed card leaves an empty column where it was
            for (const [id, slot] of Object.entries(this.slotOfId)) {
                if (!ids.includes(id) && !this.empties.includes(slot)) {
                    this.empties.push(slot);
                }
            }

            // Exactly (columns - cards) empty columns; default to the right
            const emptyCount = Math.max(0, columns - ids.length);
            this.empties = [...new Set(this.empties)].filter((slot) => slot < columns).sort((a, b) => a - b).slice(0, emptyCount);
            for (let slot = columns - 1; this.empties.length < emptyCount && slot >= 0; slot--) {
                if (!this.empties.includes(slot)) {
                    this.empties.push(slot);
                }
            }
            this.empties.sort((a, b) => a - b);

            // Columns in order: cards and placeholders; cards beyond the layout are shown full width, marked
            const items = [];
            let cardIndex = 0;
            this.slotOfId = {};
            for (let slot = 0; slot < columns; slot++) {
                if (this.empties.includes(slot)) {
                    items.push({li: this.placeholder(slot, layout, spans[slot]), span: spans[slot], slot});
                } else {
                    const li = $cardItems[cardIndex++];
                    this.slotOfId[ids[cardIndex - 1]] = slot;
                    items.push({li, span: spans[slot], slot});
                }
            }
            for (; cardIndex < $cardItems.length; cardIndex++) {
                items.push({li: $cardItems[cardIndex], span: 6, slot: null});
            }

            // Remove placeholders that are no longer used
            for (const [key, placeholder] of Object.entries(this.placeholders)) {
                if (!items.some((item) => item.li === placeholder.li)) {
                    placeholder.menu?.destroy();
                    $(placeholder.li).remove();
                    delete this.placeholders[key];
                }
            }
            // What can be added depends on the other cards in the row
            Object.values(this.placeholders).forEach((placeholder) => this.updateMenu(placeholder));

            // Only touch the DOM where something changed: other scripts observe these cards too
            for (const {li, span, slot} of items) {
                const width = this.pageWidth(span);
                const typeId = $(li).children('.element').data('entry-type-id');
                const tooNarrow = typeId !== undefined && this.tooNarrow(typeId, width);
                const tooWide = typeId !== undefined && this.tooWide(typeId, width);
                const blockedBy = typeId !== undefined ? this.blockedBy(typeId, this.cardTypeIds($(li).children('.element')[0])) : null;
                const label = slot === null
                    ? Craft.t('trinity', 'Does not fit the layout')
                    : [
                        formatPageWidth(width),
                        tooNarrow && Craft.t('trinity', 'too narrow'),
                        tooWide && Craft.t('trinity', 'too wide'),
                        blockedBy !== null && Craft.t('trinity', 'not with {type}', {type: this.typeName(blockedBy)}),
                    ].filter(Boolean).join(' · ');
                if (li.style.gridColumn !== `span ${span}`) {
                    li.style.gridColumn = `span ${span}`;
                }
                if (li.dataset.trinityWidth !== label) {
                    li.dataset.trinityWidth = label;
                    li.title = tooNarrow
                        ? Craft.t('trinity', 'This block needs a wider column: choose another layout or move it.')
                        : (tooWide ? Craft.t('trinity', 'This block needs a narrower column: choose another layout or move it.')
                            : (blockedBy !== null ? Craft.t('trinity', 'This block can’t be in the same row as {type}: choose another block or move it.', {type: this.typeName(blockedBy)}) : ''));
                }
                $(li).toggleClass('trinity__overflow', slot === null).toggleClass('trinity__invalid', tooNarrow || tooWide || blockedBy !== null);
                // The width badge takes the colours of the card (entry type colour), set by Craft on the card element
                const card = $(li).children('.element')[0];
                for (const [from, to] of [['--custom-titlebar-bg-color', '--trinity-badge-bg'], ['--custom-border-color', '--trinity-badge-border'], ['--custom-text-color', '--trinity-badge-text']]) {
                    const value = card?.style.getPropertyValue(from) ?? '';
                    if (li.style.getPropertyValue(to) !== value) {
                        value ? li.style.setProperty(to, value) : li.style.removeProperty(to);
                    }
                }
            }
            const current = $list.children('li').toArray();
            if (current.length !== items.length || items.some((item, i) => item.li !== current[i])) {
                items.forEach((item) => $list[0].appendChild(item.li));
            }

            this.hideDisallowedLayouts();
            this.hookCardDrag();
            // Fixed (all columns filled, every block fits, allowed layout): the errors of the failed save no longer apply
            if (this.$errorFields && !this.empties.length && ids.length === columns && this.isAllowed(layout)
                && !items.some(({li}) => li.classList.contains('trinity__invalid'))) {
                this.clearSavedErrors();
            }
            const canAdd = columns < 3 && this.isAllowed(LAYOUT_FOR_COLUMNS[columns + 1]);
            this.$addLeft.add(this.$addRight).toggleClass('hidden', !canAdd);
            this.positionAddButtons();
            // Content is added through the empty columns, not the field's own "Add" button
            this.nem.$createBtn?.addClass('hidden').closest('.expandable-button--collapsed').addClass('hidden');

            // Field conditions on the blocks in the row (RowCellsConditionRule) only change when the types change
            const blockTypes = [...new Set(this.cardTypeIds())].sort().join(',');
            if (this.rendered && this.config.hasBlockConditions && blockTypes !== this.blockTypes) {
                refreshConditions(this.nem.elementEditor);
            }
            this.blockTypes = blockTypes;

            ids.forEach((id) => this.knownIds.add(id));
            this.rendered = true;
            // Ignore the DOM changes made here
            this.observer.takeRecords();
            this.rendering = false;
        },

        placeNewCard($cardItems, id, slot) {
            // Cards before the new one = the filled columns left of its slot
            const filledBefore = [...Array(slot).keys()].filter((s) => !this.empties.includes(s)).length;
            const $newLi = $cardItems.filter((i, li) => String($(li).children('.element').data('id')) === id);
            const $others = $cardItems.not($newLi);
            if (filledBefore < $others.length) {
                $newLi.insertBefore($others.eq(filledBefore));
            }
            this.empties = this.empties.filter((s) => s !== slot);
            // Give the new card the slot, so render() sees it as known
            this.slotOfId[id] = slot;
            this.knownIds.add(id);
            this.nem.updateSortOrder([parseInt(id)]).catch((e) => Craft.cp.displayError(e?.response?.data?.message));
            this.render();
        },

        // Errors of a failed save
        // ---------------------------------------------------------------------

        /**
         * After a failed save Craft saves the draft again (ElementsController::actionApplyDraft()), which clears the
         * errors on nested entries: only the error summary above the form still has them. This row's errors are put
         * back on its fields from there, and the row is marked like Craft marks a block with errors.
         */
        showSavedErrors() {
            const $summaryLinks = this.$row.closest('form').find('.error-summary [data-field-error-key]');
            if (!$summaryLinks.length) {
                return;
            }
            const $fields = this.$row.find('.field[data-error-key]').filter((i, field) => rowOf(field) === this.$row[0]);
            this.$errorFields = $();
            for (const field of $fields.toArray()) {
                const messages = $summaryLinks
                    .filter((i, link) => link.dataset.fieldErrorKey === field.dataset.errorKey)
                    .toArray()
                    .map((link) => link.textContent.trim());
                if (messages.length) {
                    Craft.ui.addErrorsToField($(field), [...new Set(messages)]);
                    this.$errorFields = this.$errorFields.add(field);
                }
            }
            if (!this.$errorFields.length) {
                this.$errorFields = null;
                return;
            }
            this.$row.addClass('trinity-row--error');
            const $blocktype = this.$row.children('.titlebar').find('.blocktype').first();
            if (!$blocktype.hasClass('error')) {
                $blocktype.addClass('error trinity__error-label');
                $('<span/>', {'data-icon': 'alert', 'aria-label': Craft.t('app', 'Error'), class: 'trinity__error-icon'}).appendTo($blocktype);
            }
        },

        /** Clears the errors of the layout and columns; the row stays marked while other fields still have errors */
        clearSavedErrors() {
            const $fixed = this.$errorFields.filter(`[data-attribute="${this.config.handles.layoutField}"], [data-attribute="${this.config.handles.cellsField}"]`);
            $fixed.each((i, field) => Craft.ui.clearErrorsFromField($(field)));
            this.$errorFields = this.$errorFields.not($fixed);
            if (this.$errorFields.length) {
                return;
            }
            this.$errorFields = null;
            this.$row.removeClass('trinity-row--error');
            this.$row.children('.titlebar').find('.trinity__error-label').removeClass('error trinity__error-label');
            this.$row.children('.titlebar').find('.trinity__error-icon').remove();
        },

        // Empty columns
        // ---------------------------------------------------------------------

        placeholder(slot, layout, span) {
            const columns = SPANS[layout].length;
            const width = this.pageWidth(span);
            const key = `${slot}:${layout}`;
            if (this.placeholders[key]) {
                return this.placeholders[key].li;
            }

            const $li = $('<li/>', {class: 'trinity__empty'});
            // Read when a card from another row is dropped here
            $li[0].dataset.slot = slot;
            $li[0].dataset.pageWidth = width;
            const $addBtn = Craft.ui.createButton({icon: 'plus', label: Craft.t('trinity', 'Add content')}).addClass('dashed').appendTo($li);
            const menuId = `trinity-menu-${Math.floor(Math.random() * 1000000)}`;
            $('<div/>', {id: menuId, class: 'menu menu--disclosure'}).insertAfter($addBtn);
            $addBtn.attr({'aria-controls': menuId, 'data-disclosure-trigger': 'true'}).addClass('menubtn').disclosureMenu();
            const menu = $addBtn.data('disclosureMenu');
            // Every type that fits a column on this page is listed; the ones that can't be added here are disabled, with the reason (see updateMenu()).
            // Grouped by the entry type groups of the cells field, with a heading per group when there's more than one (as Craft's own menu)
            const items = [];
            const groups = new Map();
            for (const attributes of this.nem.settings.createAttributes ?? []) {
                if (!this.fitsPage(attributes.attributes.typeId)) {
                    continue;
                }
                const group = attributes.group || Craft.t('app', 'General');
                if (!groups.has(group)) {
                    groups.set(group, []);
                }
                groups.get(group).push(attributes);
            }
            const showGroups = groups.size > 1;
            for (const [group, groupAttributes] of groups) {
                if (showGroups) {
                    menu.addHr();
                    menu.addGroup(group, false);
                }
                for (const attributes of groupAttributes) {
                    const button = menu.addItem({
                        icon: attributes.icon ? $(attributes.icon)[0] : null,
                        label: attributes.label,
                        iconColor: attributes.color,
                        onActivate: async () => {
                            if (button.classList.contains('disabled')) {
                                return;
                            }
                            this.pendingSlot = slot;
                            $addBtn.addClass('loading');
                            await this.nem.createElement(attributes.attributes);
                            $addBtn.removeClass('loading');
                        },
                    });
                    items.push({button, typeId: attributes.attributes.typeId});
                }
            }

            if (columns > 1) {
                const $merge = $('<div/>', {class: 'trinity__merge'}).appendTo($li);
                if (slot > 0) {
                    const $left = Craft.ui.createButton({icon: 'arrow-left', label: Craft.t('trinity', 'Merge with left')}).addClass('small').appendTo($merge);
                    this.addListener($left, 'activate', () => this.merge(slot - 1));
                }
                if (slot < columns - 1) {
                    const $right = Craft.ui.createButton({icon: 'arrow-right', label: Craft.t('trinity', 'Merge with right')}).addClass('small').appendTo($merge);
                    this.addListener($right, 'activate', () => this.merge(slot));
                }
            }

            this.placeholders[key] = {li: $li[0], menu, items, width};
            this.updateMenu(this.placeholders[key]);
            return $li[0];
        },

        /** Disables the types that can't be added to this empty column, with the reason under them */
        updateMenu(placeholder) {
            for (const {button, typeId} of placeholder.items) {
                const hint = this.addHint(typeId, placeholder.width);
                button.classList.toggle('disabled', hint !== null);
                hint !== null ? button.setAttribute('aria-disabled', 'true') : button.removeAttribute('aria-disabled');
                let description = button.querySelector('.menu-item-description');
                if (!description) {
                    description = Object.assign(document.createElement('div'), {className: 'menu-item-description smalltext light'});
                    button.append(description);
                }
                if (description.textContent !== (hint ?? '')) {
                    description.textContent = hint ?? '';
                }
            }
        },

        /** Merges column i with column i + 1 (one of them is empty) */
        merge(i) {
            const layout = MERGED_LAYOUT[`${this.getLayout()}:${i}`];
            if (!layout || !this.isAllowed(layout)) {
                return;
            }
            const bothEmpty = this.empties.includes(i) && this.empties.includes(i + 1);
            this.empties = [...new Set(this.empties
                .filter((slot) => bothEmpty ? slot !== i + 1 : (slot !== i && slot !== i + 1))
                .map((slot) => slot > i + 1 ? slot - 1 : slot))];
            // The column that had content keeps it, now at position i
            for (const [id, slot] of Object.entries(this.slotOfId)) {
                if (slot === i + 1) {
                    this.slotOfId[id] = i;
                } else if (slot > i + 1) {
                    this.slotOfId[id] = slot - 1;
                }
            }
            this.setLayout(layout);
        },

        // Adding columns
        // ---------------------------------------------------------------------

        addColumnButton(side) {
            const $btn = $('<button/>', {
                type: 'button',
                class: `trinity__add trinity__add--${side}`,
                'aria-label': side === 'left' ? Craft.t('trinity', 'Add a column on the left') : Craft.t('trinity', 'Add a column on the right'),
                title: side === 'left' ? Craft.t('trinity', 'Add a column on the left') : Craft.t('trinity', 'Add a column on the right'),
                html: '<span aria-hidden="true">+</span>',
            }).appendTo(this.$cards);
            this.addListener($btn, 'activate', () => this.addColumn(side));
            return $btn;
        },

        /** The bars run along the cards, not along the buttons below them */
        positionAddButtons() {
            const list = this.$cards.children('ul.elements')[0];
            if (!list) {
                return;
            }
            for (const btn of [this.$addLeft[0], this.$addRight[0]]) {
                if (btn.style.top !== `${list.offsetTop}px` || btn.style.height !== `${list.offsetHeight}px`) {
                    btn.style.top = `${list.offsetTop}px`;
                    btn.style.height = `${list.offsetHeight}px`;
                }
            }
        },

        addColumn(side) {
            const columns = SPANS[this.getLayout()].length;
            const layout = LAYOUT_FOR_COLUMNS[columns + 1];
            if (!layout || !this.isAllowed(layout)) {
                return;
            }
            if (side === 'left') {
                this.empties = [0, ...this.empties.map((slot) => slot + 1)];
                for (const id of Object.keys(this.slotOfId)) {
                    this.slotOfId[id]++;
                }
            } else {
                this.empties = [...this.empties, columns];
            }
            this.setLayout(layout);
        },

        // Moving a card to an empty column of another row
        // ---------------------------------------------------------------------

        /** Listens to Craft's own card dragging (the nested element manager recreates it when the first card is added) */
        hookCardDrag() {
            const sort = this.nem.elementSort;
            if (!sort || sort === this.sort) {
                return;
            }
            this.sort = sort;
            sort.on('dragStart', () => this.onCardDragStart(sort));
            sort.on('drag', () => this.onCardDrag(sort));
            sort.on('dragStop', () => this.onCardDragStop(sort));
        },

        /** Empty columns in other rows of the same form; the ones this card type fits in can take it */
        onCardDragStart(sort) {
            const $element = sort.$draggee?.children('.element');
            if (!$element || $element.length !== 1) {
                return;
            }
            const typeId = $element.data('entry-type-id');
            const targets = [];
            // The buttons in empty columns would swallow the mouseup that ends the drag (see trinity.css)
            document.body.classList.add('trinity-dragging');
            for (const row of rows) {
                if (row === this || row.nem.elementEditor !== this.nem.elementEditor || !row.$cards[0].isConnected) {
                    continue;
                }
                for (const placeholder of Object.values(row.placeholders)) {
                    const li = placeholder.li;
                    if (!li.isConnected) {
                        continue;
                    }
                    const fits = row.typeFits(typeId, parseFloat(li.dataset.pageWidth)) && row.blockedBy(typeId, row.cardTypeIds()) === null;
                    li.classList.add(fits ? 'trinity__drop-target' : 'trinity__drop-target--disabled');
                    targets.push({row, li, slot: parseInt(li.dataset.slot), fits});
                }
            }
            this.dragging = {$element, targets, over: null};
        },

        onCardDrag(sort) {
            if (!this.dragging) {
                return;
            }
            const over = this.dragging.targets.find((target) => target.fits && Garnish.hitTest(sort.mouseX, sort.mouseY, target.li)) ?? null;
            if (over !== this.dragging.over) {
                this.dragging.over?.li.classList.remove('trinity__drop-target--over');
                over?.li.classList.add('trinity__drop-target--over');
                this.dragging.over = over;
            }
        },

        onCardDragStop(sort) {
            if (!this.dragging) {
                return;
            }
            // The mouse may have moved since the last drag event
            this.onCardDrag(sort);
            const {$element, targets, over} = this.dragging;
            this.dragging = null;
            document.body.classList.remove('trinity-dragging');
            for (const {li} of targets) {
                li.classList.remove('trinity__drop-target', 'trinity__drop-target--disabled', 'trinity__drop-target--over');
            }
            if (over) {
                this.moveCard($element, over.row, over.slot);
            }
        },

        /**
         * Duplicates the card's entry into the other row's column (what Copy + Paste does, without touching the clipboard),
         * then deletes it here. In that order, a failure leaves a copy behind instead of losing the content.
         */
        async moveCard($element, target, slot) {
            const $li = $element.parent().addClass('trinity__moving');
            Craft.cp.announce(Craft.t('app', 'Loading'));
            try {
                // Both rows in the draft first: this can give the cards and the owners new IDs
                await this.nem.markAsDirty();
                await target.nem.markAsDirty();
                const elementId = $element.data('id');
                const response = await Craft.sendActionRequest('POST', 'elements/bulk-duplicate', {
                    data: {
                        elements: [{
                            type: $element.data('type'),
                            id: this.nem.elementEditor?.getDraftElementId(elementId) || elementId,
                            siteId: $element.data('site-id'),
                        }],
                        newAttributes: {
                            primaryOwnerId: target.nem.settings.ownerId,
                            ownerId: target.nem.settings.ownerId,
                            fieldId: target.nem.settings.fieldId,
                            siteId: target.nem.settings.ownerSiteId,
                        },
                    },
                });
                const newElements = response.data.newElements ?? [];
                if (!newElements.length) {
                    throw new Error();
                }
                // The new card goes into the column it was dropped on (placeNewCard() also saves its position)
                target.pendingSlot = slot;
                await target.nem.addElementCards(newElements);
            } catch (e) {
                $li.removeClass('trinity__moving');
                Craft.cp.displayError(e?.response?.data?.message ?? Craft.t('app', 'A server error occurred.'));
                return;
            }

            try {
                await this.nem.deleteElement($element);
            } catch (e) {
                // deleteElement() already shows the error; the block now exists in both rows
                $li.removeClass('trinity__moving');
                return;
            }
            target.nem.elementEditor?.checkForm(true);
        },

        destroy() {
            rows.delete(this);
            this.observer?.disconnect();
            this.resizeObserver?.disconnect();
            for (const placeholder of Object.values(this.placeholders)) {
                placeholder.menu?.destroy();
                $(placeholder.li).remove();
            }
            this.$addLeft?.remove();
            this.$addRight?.remove();
            this.$cards.removeClass('trinity');
            this.base();
        },
    });

    /**
     * The "Space above" controls of a content builder: the first row has nothing above it (control hidden), and a row whose
     * background differs from the row above keeps its own padding (control disabled). Kept up to date when rows move, a
     * background changes or a condition re-renders a row.
     */
    const watchedBuilders = new WeakMap();

    /** A row's background: the checked/selected value of rowBackgroundField, or the default when it's empty or hidden */
    const rowBackground = (block, config) => {
        const inputs = [...block.querySelectorAll(`[name$="[fields][${config.handles.rowBackgroundField}]"]`)];
        const input = inputs.find((input) => !['radio', 'checkbox'].includes(input.type) || input.checked);
        return input?.value || config.rowBackgroundDefault || '';
    };

    const updateBuilder = (container) => {
        const config = watchedBuilders.get(container)?.config;
        if (!config) {
            return;
        }
        let previous = null;
        container.querySelectorAll(':scope > .matrixblock').forEach((block, index) => {
            block.classList.toggle('trinity-first-row', index === 0);
            const background = config.handles.rowBackgroundField ? rowBackground(block, config) : null;
            const inactive = index > 0 && background !== previous;
            previous = background;
            const bar = block.querySelector(':scope > .trinity-spacing');
            if (!bar) {
                return;
            }
            block.classList.toggle('trinity-spacing-inactive', inactive);
            bar.title = inactive
                ? Craft.t('trinity', 'Another background than the row above: the spacing doesn’t apply')
                : Craft.t('trinity', 'Only between rows with the same background');
            bar.querySelectorAll('.trinity-spacing__option').forEach((button) => {
                button.disabled = inactive;
            });
        });
    };

    const watchBuilder = (container, config) => {
        if (!container || watchedBuilders.has(container)) {
            return;
        }
        const state = {config, frame: null};
        watchedBuilders.set(container, state);
        const schedule = () => {
            cancelAnimationFrame(state.frame);
            state.frame = requestAnimationFrame(() => updateBuilder(container));
        };
        // Rows added, moved or removed, and rows re-rendered by a condition refresh
        new MutationObserver(schedule).observe(container, {childList: true, subtree: true});
        // A background chosen in a row
        container.addEventListener('change', schedule);
        updateBuilder(container);
    };

    /** The row a field belongs to: its block in the content builder, or the form when the row is edited on its own (e.g. in a slideout) */
    const rowOf = (element) => $(element).closest('.matrixblock')[0] ?? $(element).closest('form')[0] ?? null;

    // Every cells field has a [data-trinity] config element; also the ones added later (new blocks, slideouts). The Matrix JS may
    // initialise after the markup, so this waits for it.
    const initRow = (configEl, attempt = 0) => {
        if (configEl.dataset.trinityRow) {
            return;
        }
        const config = JSON.parse(configEl.dataset.trinity);
        const cellsField = configEl.closest(`[data-attribute="${config.handles.cellsField}"]`);
        const row = cellsField ? rowOf(cellsField) : null;
        const cardsContainer = cellsField?.querySelector('.nested-element-cards');
        const nem = cardsContainer ? $(cardsContainer).data('nestedElementManager') : null;
        const listbox = $(row).find(`[data-attribute="${config.handles.layoutField}"] .btngroup`).first().data('listbox');
        if (!row || !nem || !listbox) {
            if (attempt < 50) {
                setTimeout(() => initRow(configEl, attempt + 1), 100);
            }
            return;
        }
        SPANS ??= Object.fromEntries(Object.entries(config.layouts).map(([layout, fractions]) => [layout, fractions.map((fraction) => Math.round(fraction * 6))]));
        configEl.dataset.trinityRow = 'true';
        new GridRow(row, cardsContainer, nem, listbox, config);
    };

    const scan = (root) => {
        $(root).find('[data-trinity]').addBack('[data-trinity]').each((i, configEl) => initRow(configEl));
    };

    $(() => {
        scan(document.body);
        new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType === Node.ELEMENT_NODE && (node.matches('[data-trinity]') || node.querySelector('[data-trinity]'))) {
                        scan(node);
                    }
                }
            }
        }).observe(document.body, {childList: true, subtree: true});
    });
})(jQuery);
