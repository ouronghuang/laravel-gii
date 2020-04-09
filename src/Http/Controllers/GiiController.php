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

    public function store(Request $request)
    {
        $module = strtolower($request->input('module'));
        $table = strtolower($request->input('table'));
        $columns = collect($request->input('columns'))
            ->map(function ($v) {
                $v['name'] = strtolower($v['name']);

                return $v;
            });

        $data = [];

        // $data[] = $this->buildModel($table, $columns);
        $data[] = $this->buildMigration($table, $columns);
        // $data[] = $this->buildController($module, $table, $columns);
        // $data[] = $this->buildResource($module, $table, $columns);
        // $data[] = $this->buildRequest($module, $table, $columns);

        return $data;
    }

    /**
     * 创建模型文件.
     *
     * @param string $table
     * @param array  $columns
     *
     * @return string
     */
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

    /**
     * 创建迁移文件.
     *
     * @param string $table
     * @param array  $columns
     *
     * @return string
     */
    protected function buildMigration($table, $columns)
    {
        $timestamp = date('Y_m_d_His');
        $filename = "create_{$table}_table";

        $path = "database/migrations/{$timestamp}_{$filename}.php";

        $columns = collect($columns)
            ->map(function ($v) {
                $tmp = '$table->'."{$v['type']}('{$v['name']}')";

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

    /**
     * 创建控制器文件.
     *
     * @param string $module
     * @param string $table
     * @param array  $columns
     *
     * @return string
     */
    protected function buildController($module, $table, $columns)
    {
        $dummyModel = studly_case(str_singular($table));

        $dummyClass = str_plural($dummyModel).'Controller';

        $path = 'app/Http/Controllers/'.($module ? ucfirst($module).'/' : '')."{$dummyClass}.php";

        $password = collect($columns)
            ->filter(function ($v) {
                return $v['password'];
            })
            ->pluck('name')
            ->map(function ($v) {
                return <<<EOT
        if (\$request->input('{$v}')) {
            \$data['{$v}'] = bcrypt(\$request->input('{$v}'));
        }
EOT;
            })
            ->implode("\n\n");

        $password = "\n{$password}\n";

        $columns = collect($columns)
            ->filter(function ($v) {
                return $v['writable'] && !$v['password'];
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
            'DummyPassword',
        ];

        $replace = [
            $module ? '\\'.ucfirst($module) : '',
            "{$dummyModel}Request",
            "{$dummyModel}Resource",
            $dummyModel,
            camel_case($dummyModel),
            $dummyClass,
            ($module ? "{$module}." : '').$table,
            $columns,
            $password,
        ];

        $this->createStub('controller', $path, $search, $replace);

        return $path;
    }

    /**
     * 创建资源文件.
     *
     * @param string $module
     * @param string $table
     * @param array  $columns
     *
     * @return string
     */
    protected function buildResource($module, $table, $columns)
    {
        $dummyClass = studly_case(str_singular($table)).'Resource';

        $path = 'app/Http/Resources/'.($module ? ucfirst($module).'/' : '')."{$dummyClass}.php";

        $columns = collect($columns)
            ->filter(function ($v) {
                return $v['readable'];
            })
            ->pluck('name')
            ->map(function ($v) {
                return "'{$v}' => ".'$this->'."{$v},";
            })
            ->implode("\n            ");

        $search = [
            'DummyModule',
            'DummyClass',
            'DummyRoute',
            'DummyColumns',
        ];

        $replace = [
            $module ? '\\'.ucfirst($module) : '',
            $dummyClass,
            ($module ? "{$module}." : '').$table,
            $columns,
        ];

        $this->createStub('resource', $path, $search, $replace);

        return $path;
    }

    /**
     * 创建表单验证文件.
     *
     * @param string $module
     * @param string $table
     * @param array  $columns
     *
     * @return string
     */
    protected function buildRequest($module, $table, $columns)
    {
        $module = ucfirst($module);

        $dummyClass = studly_case(str_singular($table)).'Request';

        $base = 'app/Http/Requests'.($module ? "/{$module}" : '');
        $path = "{$base}/{$dummyClass}.php";
        $pathFormRequest = "{$base}/FormRequest.php";

        $rules = collect($columns)
            ->filter(function ($v) {
                return $v['validation'];
            })
            ->map(function ($v) use ($table) {
                $rule = "'{$v['name']}' => '{$v['rules']}";

                $rule .= $v['unique'] ? "|unique:{$table},{$v['name']},'".'.$this->input(\'id\')' : "'";

                $rule .= ',';

                return $rule;
            })
            ->implode("\n            ");

        $attributes = collect($columns)
            ->filter(function ($v) {
                return $v['validation'];
            })
            ->map(function ($v) {
                return "'{$v['name']}' => '{$v['comment']}',";
            })
            ->implode("\n            ");

        $search = [
            'DummyModule',
            'DummyClass',
            'DummyRule',
            'DummyAttribute',
        ];

        $replace = [
            $module ? "\\{$module}" : '',
            $dummyClass,
            $rules,
            $attributes,
        ];

        $this->createStub('form-request-base', $pathFormRequest, $search, $replace);

        $this->createStub('form-request', $path, $search, $replace);

        return $path;
    }

    /**
     * 创建表单验证文件.
     *
     * @param string $module
     * @param string $table
     * @param array  $columns
     *
     * @return string
     */
    protected function buildViews($module, $table, $columns)
    {
    }

    /**
     * 生成相应文件.
     *
     * @param string $type
     * @param string $path
     * @param array  $search
     * @param array  $replace
     *
     * @return void
     */
    protected function createStub($type, $path, $search = [], $replace = [])
    {
        $file = app(Filesystem::class);

        if ($file->exists($path = base_path($path))) {
            return;
        }

        $this->mkdir($path);

        $stub = $file->get(__DIR__."/../../../stubs/{$type}.stub");

        $stub = str_replace($search, $replace, $stub);

        $file->put($path, $stub);
    }

    /**
     * 创建文件夹.
     *
     * @param string $path
     *
     * @return bool
     */
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
