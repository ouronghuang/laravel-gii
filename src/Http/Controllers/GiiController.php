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
        $module = $request->input('module');
        $table = $request->input('table');
        $columns = $request->input('columns');

        $this->buildModel($table, $columns);
        $this->buildMigration($table, $columns);

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

    protected function createStub($type, $path, $search, $replace)
    {
        $file = app(Filesystem::class);

        if ($file->exists($path = base_path($path))) {
            return;
        }

        $stub = $file->get(__DIR__ . "/../../../stubs/{$type}.stub");

        $stub = str_replace($search, $replace, $stub);

        $file->put($path, $stub);
    }
}
