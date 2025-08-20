<?php

// config for Coolsam/Modules
return [
    'mode' => \Coolsam\Modules\Enums\ConfigMode::BOTH->value, // 'plugins' or 'panels', determines how the Filament Modules are registered
    'auto-register-plugins' => true, // whether to auto-register plugins from various modules in the Panel. Only relevant if 'mode' is set to 'plugins'.
    'clusters' => [
        'enabled' => true, // whether to enable the clusters feature which allows you to group each module's filament resources and pages into a cluster
        'use-top-navigation' => true, // display the main cluster menu in the top navigation and the sub-navigation in the side menu, which improves the UI
    ],
    'panels' => [
        'group' => 'Panels', // the group name for the panels in the navigation
        'group-icon' => \Filament\Support\Icons\Heroicon::OutlinedRectangleStack,
        'group-sort' => 0, // the sort order of the panels group in the navigation
        'open-in-new-tab' => false, // whether to open the panels in a new tab
    ],
];
