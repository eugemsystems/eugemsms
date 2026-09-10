<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Module dependency graph (Book A CORE-02 §4, BR-CORE-02-013)
    |--------------------------------------------------------------------------
    |
    | A module cannot be enabled for a school while any module it declares
    | here as a dependency is disabled for that school. Keyed and valued by
    | `school_modules.module_code`. Each book adds its own modules' entries
    | here as they're built — nothing downstream of CORE exists yet, so
    | this starts empty rather than guessing at a graph ahead of the books
    | that will actually define it.
    |
    */
    'module_dependencies' => [
        //
    ],

];
