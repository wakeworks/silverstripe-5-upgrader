<?php

declare(strict_types=1);

use Rector\Arguments\Rector\ClassMethod\ReplaceArgumentDefaultValueRector;
use Rector\Arguments\ValueObject\ReplaceArgumentDefaultValue;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\MethodCall\RenameMethodRector;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Rector\Renaming\ValueObject\MethodCallRename;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(RenameMethodRector::class, [
        new MethodCallRename(
            'UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows',
            'setUpdateVersionedStage',
            'setRepublishLiveRecords'
        )
    ]);

    $rectorConfig->ruleWithConfiguration(ReplaceArgumentDefaultValueRector::class, [
        new ReplaceArgumentDefaultValue(
            'UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows',
            'setRepublishLiveRecords',
            0,
            'Live',
            true
        ),
        new ReplaceArgumentDefaultValue(
            'UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows',
            'setRepublishLiveRecords',
            0,
            'Draft',
            false
        )
    ]);

    $rectorConfig->ruleWithConfiguration(RenameClassRector::class, [
        'UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows' => 'Symbiote\GridFieldExtensions\GridFieldOrderableRows'
    ]);
};
