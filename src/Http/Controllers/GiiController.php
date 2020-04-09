<?php

namespace Orh\LaravelGii\Http\Controllers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;

class GiiController
{
    public function create()
    {
        return view('gii::gii.create');
    }

    public function store(Request $request, Filesystem $file)
    {
        $module = strtolower($request->input('module'));
        $table = strtolower($request->input('table'));
        $columns = collect($request->input('columns'))
            ->map(function ($v) {
                $v['name'] = strtolower($v['name']);

                return $v;
            });

        $this->buildModel($table, $columns);
        // $this->buildMigration($table, $columns);
        $this->buildController($module, $table, $columns);

        return;
    }

    protected function buildModel($table, $columns)
    {
        $dummyClass = studly_case(str_singular($table));

        $path = "app/Models/{$dummyClass}.php";

        $columns = collect($columns)
            ->filter(function ($v) {
                return $v['writable'];
            })
            ->pluck('name')
            ->map(function ($v) {
                return "'{$v}',";
            })
            ->implode("\n        ");

        $search = [
            'DummyClass',
            'DummyColumns',
        ];

        $replace = [
            $dummyClass,
            $columns,
        ];

        $this->createStub('model', $path, $search, $replace);

        return $path;
    }

    protected function buildMigration($table, $columns)
    {
        $timestamp = date('Y_m_d_His');
        $filename = "create_{$table}_table";

        $path = "database/migrations/{$timestamp}_{$filename}.php";

        $columns = collect($columns)
            ->map(function ($v) {
                $tmp = '$table->' . "{$v['type']}('{$v['name']}')";

                $tmp .= $v['unique'] ? '->unique()' : '';

                $tmp .= $v['nullable'] ? '->nullable()' : '';

                $tmp .= strlen($v['default']) ? "->default('{$v['default']}')" : '';

                $tmp .= strlen($v['comment']) ? "->comment('{$v['comment']}')" : '';

                $tmp .= ';';

                return $tmp;
            })
            ->implode("\n            ");

        $search = [
            'DummyClass',
            'DummyTable',
            'DummyColumns',
        ];

        $replace = [
            studly_case($filename),
            $table,
            $columns,
        ];

        $this->createStub('migration', $path, $search, $replace);

        return $path;
    }

    protected function buildController($module, $table, $columns)
    {
        $dummyModel = studly_case(str_singular($table));

        $dummyClass = str_plural($dummyModel) . 'Controller';

        $path = 'app/Http/Controllers/' . ($module ? ucfirst($module) . '/' : '') . "{$dummyClass}.php";

        $columns = collect($columns)
            ->filter(function ($v) {
                return $v['writable'];
            })
            ->pluck('name')
            ->map(function ($v) {
                return "'{$v}',";
            })
            ->implode("\n            ");

        $search = [
            'DummyModule',
            'DummyRequest',
            'DummyResource',
            'DummyModel',
            'dummyModel',
            'DummyClass',
            'DummyView',
            'DummyColumns',
        ];

        $replace = [
            $module ? '\\' . ucfirst($module) : '',
            "{$dummyModel}Request",
            "{$dummyModel}Resource",
            $dummyModel,
            camel_case($dummyModel),
            $dummyClass,
            ($module ? "{$module}." : '') . "{$table}",
            $columns,
        ];

        $this->createStub('controller', $path, $search, $replace);

        return $path;
    }

    protected function createStub($type, $path, $search, $replace)
    {
        $file = app(Filesystem::class);

        if ($file->exists($path = base_path($path))) {
            return;
        }

        $this->mkdir($path);

        $stub = $file->get(__DIR__ . "/../../../stubs/{$type}.stub");

        $stub = str_replace($search, $replace, $stub);

        $file->put($path, $stub);
    }

    protected function mkdir($path)
    {
        $directory = dirname($path);

        if (!is_dir($directory) && (false === @mkdir($directory, 0777, true)) && !is_dir($directory)) {
            return false;
        } elseif (!is_writable($directory)) {
            return false;
        }

        return true;
    }
}
