<?php

return [
    // Condition rules
    'Column span' => 'Largeur de la colonne dans la rangée',
    'Column width' => 'Largeur de la colonne sur la page',
    'Row cells' => 'Cellules de la rangée',
    'full page width' => 'pleine largeur de la page',
    'full row' => 'rangée entière',
    '{width} of the page' => '{width} de la page',
    '{width} of the row' => '{width} de la rangée',

    // Validation
    'This layout has columns that are too narrow for this page, choose a layout with wider columns.' => 'Cette mise en page a des colonnes trop étroites pour cette page, choisissez une mise en page avec des colonnes plus larges.',
    'This layout has {columns, plural, =1{1 column} other{# columns}}, but there {cells, plural, =1{is 1 block} other{are # blocks}}. Add or remove blocks, or choose another layout.' => 'Cette mise en page a {columns, plural, =1{1 colonne} other{# colonnes}}, mais il y a {cells, plural, =1{1 bloc} other{# blocs}}. Ajoutez ou supprimez des blocs, ou choisissez une autre mise en page.',
    '“{type}” does not fit in column {column} ({width}), it needs at least {minWidth}. Choose a wider layout or another block.' => '« {type} » ne tient pas dans la colonne {column} ({width}), il lui faut au moins {minWidth}. Choisissez une mise en page plus large ou un autre bloc.',
    '“{type}” is too wide in column {column} ({width}), it can be at most {maxWidth}. Choose a layout with narrower columns or another block.' => '« {type} » est trop large dans la colonne {column} ({width}), il peut faire au plus {maxWidth}. Choisissez une mise en page avec des colonnes plus étroites ou un autre bloc.',
    '“{type}” and “{other}” can’t be in the same row. Choose another block.' => '« {type} » et « {other} » ne peuvent pas être dans la même rangée. Choisissez un autre bloc.',

    // Control panel layer
    'Add a column on the left' => 'Ajouter une colonne à gauche',
    'Add a column on the right' => 'Ajouter une colonne à droite',
    'Add content' => 'Ajouter du contenu',
    'Does not fit the layout' => 'Ne correspond pas à la mise en page',
    'Max. width {width}' => 'Largeur max. {width}',
    'Min. width {width}' => 'Largeur min. {width}',
    'Merge with left' => 'Fusionner avec la gauche',
    'Merge with right' => 'Fusionner avec la droite',
    'Not with {type}' => 'Pas avec {type}',
    'Not available: the row above has another background' => 'Non disponible : la rangée au-dessus a un autre arrière-plan',
    'Only between rows with the same background' => 'Uniquement entre des rangées avec le même arrière-plan',
    'Space above' => 'Espace au-dessus',
    'not with {type}' => 'pas avec {type}',
    'This block can’t be in the same row as {type}: choose another block or move it.' => 'Ce bloc ne peut pas être dans la même rangée que {type} : choisissez un autre bloc ou déplacez-le.',
    'This block needs a narrower column: choose another layout or move it.' => 'Ce bloc a besoin d’une colonne plus étroite : choisissez une autre mise en page ou déplacez-le.',
    'This block needs a wider column: choose another layout or move it.' => 'Ce bloc a besoin d’une colonne plus large : choisissez une autre mise en page ou déplacez-le.',
    'This layout has columns that are too narrow for this page.' => 'Cette mise en page a des colonnes trop étroites pour cette page.',
    'too narrow' => 'trop étroit',
    'too wide' => 'trop large',
];
