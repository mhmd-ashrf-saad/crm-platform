<?php

namespace Webkul\Admin\DataGrids\Settings\DataTransfer;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;
use Webkul\DataTransfer\Helpers\Import;

class ImportDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        return DB::table('imports')
            ->select(
                'id',
                'state',
                'file_path',
                'error_file_path',
                'started_at',
                'completed_at',
                'type',
                'summary',
            );
    }

    /**
     * Prepare Columns.
     */
    public function prepareColumns(): void
    {
        /**
         * The importer titles the create form's type selector already shows, keyed by the identifier
         * `imports.type` stores. Same source, so the listing and the form cannot label the same
         * importer differently, and the dropdown below submits the identifier as its value so
         * filtering keeps matching `imports.type`.
         */
        $importTypes = collect(config('importers'))
            ->map(fn ($importer, $type) => $this->importTypeLabel($type))
            ->all();

        /**
         * The states `imports.state` can hold, read from the constants the import helper writes
         * rather than from a second copy of their names, with the label each one renders.
         *
         * Only the rendered label moves. The column still sorts, searches and filters on
         * `imports.state`, and the dropdown filter below submits the identifier as its value, so
         * nothing starts matching a row by its translated name. A state whose label a locale has not
         * translated falls back to the identifier rather than rendering a translation key.
         */
        $stateLabels = collect([
            Import::STATE_PENDING,
            Import::STATE_VALIDATED,
            Import::STATE_PROCESSING,
            Import::STATE_LINKING,
            Import::STATE_INDEXING,
            Import::STATE_COMPLETED,
        ])
            ->mapWithKeys(function (string $state) {
                $key = 'admin::app.settings.data-transfer.imports.index.datagrid.states.'.$state;

                return [$state => app('translator')->has($key) ? trans($key) : $state];
            })
            ->all();

        $this->addColumn([
            'index' => 'id',
            'label' => trans('admin::app.settings.data-transfer.imports.index.datagrid.id'),
            'type' => 'integer',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'type',
            'label' => trans('admin::app.settings.data-transfer.imports.index.datagrid.type'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => collect($importTypes)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'sortable' => true,
            'closure' => fn ($row) => $importTypes[$row->type] ?? $row->type,
        ]);

        $this->addColumn([
            'index' => 'state',
            'label' => trans('admin::app.settings.data-transfer.imports.index.datagrid.state'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => collect($stateLabels)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'sortable' => true,
            'closure' => fn ($row) => $stateLabels[$row->state] ?? $row->state,
        ]);

        $this->addColumn([
            'index' => 'file_path',
            'label' => trans('admin::app.settings.data-transfer.imports.index.datagrid.uploaded-file'),
            'type' => 'string',
            'closure' => function ($row) {
                return '<a href="'.route('admin.settings.data_transfer.imports.download', $row->id).'" class="cursor-pointer text-blue-600 hover:underline">'.$row->file_path.'<a>';
            },
        ]);

        $this->addColumn([
            'index' => 'error_file_path',
            'label' => trans('admin::app.settings.data-transfer.imports.index.datagrid.error-file'),
            'type' => 'string',
            'closure' => function ($row) {
                if (empty($row->error_file_path)) {
                    return '';
                }

                return '<a href="'.route('admin.settings.data_transfer.imports.download_error_report', $row->id).'" class="cursor-pointer text-blue-600 hover:underline">'.$row->error_file_path.'<a>';
            },
        ]);

        $this->addColumn([
            'index' => 'started_at',
            'label' => trans('admin::app.settings.data-transfer.imports.index.datagrid.started-at'),
            'type' => 'date',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'date_range',
            'sortable' => true,
            /**
             * Both timestamps are nullable in `imports`, and `completed_at` is null for every
             * import that has not finished. `core()->formatDate(null)` parses to *now*, so an
             * unguarded closure would have shown a running import a completion time of the moment
             * the page was rendered. `--` is the empty-cell marker the leads grid already uses.
             */
            'closure' => fn ($row) => $row->started_at ? core()->formatDate($row->started_at) : '--',
        ]);

        $this->addColumn([
            'index' => 'completed_at',
            'label' => trans('admin::app.settings.data-transfer.imports.index.datagrid.completed-at'),
            'type' => 'date',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'date_range',
            'sortable' => true,
            'closure' => fn ($row) => $row->completed_at ? core()->formatDate($row->completed_at) : '--',
        ]);

        $this->addColumn([
            'index' => 'summary',
            'label' => trans('admin::app.settings.data-transfer.imports.index.datagrid.summary'),
            'type' => 'string',
            'closure' => function ($row) {
                if (empty($row->summary)) {
                    return '';
                }

                $summary = json_decode($row->summary, true);

                $stats = [];

                foreach ($summary as $type => $value) {
                    $stats[] = trans('admin::app.settings.data-transfer.imports.index.datagrid.'.$type).': '.$summary[$type];
                }

                return implode(', ', $stats);
            },
        ]);
    }

    /**
     * The label for an importer `type`, or the stored identifier when the locale has none.
     *
     * The title lives in `config/importers.php` because the create form reads it from there; a type
     * with no config entry, or a config entry the active locale has not translated, falls back to the
     * identifier rather than rendering a translation key.
     */
    protected function importTypeLabel(string $type): string
    {
        $key = config('importers.'.$type.'.title');

        return $key && app('translator')->has($key) ? trans($key) : $type;
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.automation.data_transfer.imports.import')) {
            $this->addAction([
                'index' => 'import',
                'icon' => 'icon-import',
                'title' => trans('admin::app.settings.data-transfer.imports.index.datagrid.import'),
                'method' => 'GET',
                'url' => function ($row) {
                    return route('admin.settings.data_transfer.imports.import', $row->id);
                },
            ]);
        }

        if (bouncer()->hasPermission('settings.automation.data_transfer.imports.edit')) {
            $this->addAction([
                'index' => 'edit',
                'icon' => 'icon-edit',
                'title' => trans('admin::app.settings.data-transfer.imports.index.datagrid.edit'),
                'method' => 'GET',
                'url' => function ($row) {
                    return route('admin.settings.data_transfer.imports.edit', $row->id);
                },
            ]);
        }

        if (bouncer()->hasPermission('settings.automation.data_transfer.imports.delete')) {
            $this->addAction([
                'index' => 'delete',
                'icon' => 'icon-delete',
                'title' => trans('admin::app.settings.data-transfer.imports.index.datagrid.delete'),
                'method' => 'DELETE',
                'url' => function ($row) {
                    return route('admin.settings.data_transfer.imports.delete', $row->id);
                },
            ]);
        }
    }
}
