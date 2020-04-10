<?php

namespace Orh\LaravelGii\Http\Controllers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;

class GiiController
{
    protected $file;

    public function __construct()
    {
        $this->file = app(Filesystem::class);
    }

    public function create()
    {
        return view('gii::gii.create');
    }

    public function store(Request $request)
    {
        $module = strtolower($request->input('module'));
        $table = strtolower($request->input('table'));
        $comment = strtolower($request->input('comment'));
        $columns = collect($request->input('columns'))
            ->map(function ($v) {
                $v['name'] = strtolower($v['name']);

                return $v;
            });

        $data = [];

        // $data[] = $this->buildModel($table, $columns);
        // $data[] = $this->buildMigration($table, $columns);
        // $data[] = $this->buildController($module, $table, $columns);
        // $data[] = $this->buildResource($module, $table, $columns);
        // $data[] = $this->buildRequest($module, $table, $columns);
        // $data[] = $this->buildViewIndex($module, $table, $comment, $columns);
        // $data[] = $this->buildViewAction($module, $table, $comment, $columns);
        $data[] = $this->buildRoute($module, $table);

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
                return $v['form_type'] == 'password';
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
                return $v['writable'] && $v['form_type'] != 'password';
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
                return $v['validation'] && $v['form_type'] != 'password';
            })
            ->map(function ($v) use ($table) {
                $rule = "'{$v['name']}' => '{$v['rules']}";

                $rule .= $v['unique'] ? "|unique:{$table},{$v['name']}" : '';

                $rule .= "',";

                return $rule;
            })
            ->implode("\n            ");

        $editRules = collect($columns)
            ->filter(function ($v) {
                return $v['unique'];
            })
            ->map(function ($v) use ($table) {
                return "\$rules['{$v['name']}'] .= ','.\$this->input('id');";
            })
            ->implode("\n            ");

        $createRules = collect($columns)
            ->filter(function ($v) {
                return $v['form_type'] == 'password';
            })
            ->map(function ($v) use ($table) {
                return "\$rules['{$v['name']}'] = 'required|string';";
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
            'DummyEditRules',
            'DummyCreateRules',
            'DummyAttribute',
        ];

        $replace = [
            $module ? "\\{$module}" : '',
            $dummyClass,
            $rules,
            $editRules,
            $createRules,
            $attributes,
        ];

        $this->createStub('form-request-base', $pathFormRequest, $search, $replace);

        $this->createStub('form-request', $path, $search, $replace);

        return $path;
    }

    /**
     * 创建视图列表文件.
     *
     * @param string $module
     * @param string $table
     * @param string $comment
     * @param array  $columns
     *
     * @return string
     */
    protected function buildViewIndex($module, $table, $comment, $columns)
    {
        $path = "resources/views/{$module}/{$table}/index.blade.php";

        $search = [
            'DummyModule',
            'DummyComment',
            'DummyTable',
            'DummyHeads',
            'DummyBodies',
        ];

        $heads = collect($columns)
            ->filter(function ($v) {
                return $v['readable'];
            })
            ->pluck('comment')
            ->map(function ($v) {
                return <<<EOT
                  <th scope="col">{$v}</th>
EOT;
            })
            ->implode("\n");

        $heads = "\n{$heads}\n";

        $bodies = collect($columns)
            ->filter(function ($v) {
                return $v['readable'];
            })
            ->map(function ($v) {
                if ($v['form_type'] == 'file') {
                    $td = <<<EOT
                  <td>
                    <a :href="v.{$v['name']}" target="_blank">
                      @{{ v.{$v['name']} }}
                    </a>
                  </td>
EOT;
                } else {
                    $td = <<<EOT
                  <td>@{{ v.{$v['name']} }}</td>
EOT;
                }

                return $td;
            })
            ->implode("\n");

        $bodies = "\n{$bodies}\n";

        $replace = [
            $module,
            $comment,
            $table,
            $heads,
            $bodies,
        ];

        $this->createStub('view-index', $path, $search, $replace);

        return $path;
    }

    /**
     * 创建视图操作文件.
     *
     * @param string $module
     * @param string $table
     * @param string $comment
     * @param array  $columns
     *
     * @return string
     */
    protected function buildViewAction($module, $table, $comment, $columns)
    {
        $path = "resources/views/{$module}/{$table}/create_and_edit.blade.php";

        $model = camel_case(str_singular($table));

        $search = [
            'DummyModule',
            'DummyModel',
            'DummyComment',
            'DummyTable',
            'DummyForms',
        ];

        $forms = collect($columns)
            ->filter(function ($v) {
                return $v['writable'];
            })
            ->map(function ($v) use ($model) {
                if ($v['form_type'] == 'password') {
                    $form = <<<EOT
          <form-item label="{$v['comment']}" prop="{{ \${$model}->id ? '' : '{$v['name']}' }}">
            <i-input type="password" v-model="formValidate.{$v['name']}" placeholder="{{ \${$model}->id ? '为空则不修改' : '请输入{$v['comment']}' }}" clearable></i-input>
          </form-item>
EOT;
                } elseif ($v['form_type'] == 'number') {
                    $form = <<<EOT
          <form-item label="{$v['comment']}" prop="{$v['name']}">
            <input-number :min="0" v-model="formValidate.{$v['name']}" class="w-100"></input-number>
          </form-item>
EOT;
                } elseif ($v['form_type'] == 'file') {
                    $form = <<<EOT
          <form-item label="{$v['comment']}">
            <input ref="{$v['name']}" class="d-none" type="file" @change="e => handleFileChange(e, '{$v['name']}')">
            <i-input v-model="formValidate.{$v['name']}" placeholder="请输入{$v['comment']}地址或选择上传文件">
              <icon class="cp" type="md-cloud-upload" slot="suffix" @click="\$refs.{$v['name']}.click()"></icon>
            </i-input>
          </form-item>
          <form-item label="{$v['comment']}预览" v-if="formValidate.{$v['name']}">
            <a :href="formValidate.{$v['name']}" target="_blank">
              @{{ formValidate.{$v['name']} }}
            </a>
          </form-item>
EOT;
                } else {
                    $form = <<<EOT
          <form-item label="{$v['comment']}" prop="{$v['name']}">
            <i-input v-model="formValidate.{$v['name']}" placeholder="请输入{$v['comment']}" clearable></i-input>
          </form-item>
EOT;
                }

                return $form;
            })
            ->implode("\n");

        $forms = "\n{$forms}\n";

        $replace = [
            $module,
            $model,
            $comment,
            $table,
            $forms,
        ];

        $this->createStub('view-action', $path, $search, $replace);

        return $path;
    }

    /**
     * 创建路由文件.
     *
     * @param string $module
     * @param string $table
     *
     * @return string
     */
    protected function buildRoute($module, $table)
    {
        $path = "routes/{$module}.php";

        $this->createStub('route', $path);

        $controller = str_plural($table).'Controller';

        $stub = $this->file->get(base_path($path));

        $stub .= "\nRoute::resource('{$table}', '{$controller}')->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);\n";

        $this->file->put(base_path($path), $stub);

        return $path;
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
        if ($this->file->exists($path = base_path($path))) {
            return;
        }

        $this->mkdir($path);

        $stub = $this->file->get(__DIR__."/../../../stubs/{$type}.stub");

        $stub = str_replace($search, $replace, $stub);

        $this->file->put($path, $stub);
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
