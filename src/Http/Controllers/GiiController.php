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

        return [
            $this->buildModel($table, $columns),
            $this->buildMigration($table, $columns),
            $this->buildController($module, $table, $columns),
            $this->buildResource($module, $table, $columns),
            $this->buildRequest($module, $table, $columns),
            $this->buildViewIndex($module, $table, $comment, $columns),
            $this->buildViewAction($module, $table, $comment, $columns),
            $this->buildRoute($module, $table),
            $this->buildJs($module, $table, $columns),
        ];
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

                if (strlen($v['default'])) {
                    if (is_numeric($v['default'])) {
                        $v['default'] = (int)$v['default'];
                    } else {
                        $v['default'] = "'{$v['default']}'";
                    }

                    if ($v['type'] == 'boolean') {
                        $v['default'] = (boolean)$v['default'];
                    }

                    $tmp .= "->default({$v['default']})";
                }

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
                return 'password' == $v['form_type'];
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
                return $v['writable'] && 'password' != $v['form_type'];
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
                return $v['validation'] && 'password' != $v['form_type'];
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
                return 'password' == $v['form_type'];
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
                if ('file' == $v['form_type']) {
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

        $search = [
            'DummyLayout',
            'DummyComment',
            'DummyComponentPrefix',
            'DummyComponentName',
            'DummyTable',
            'DummyHeads',
            'DummyBodies',
        ];

        $replace = [
            $module ? "{$module}." : '',
            $comment,
            $module ? "-{$module}" : '',
            str_replace('_', '-', $table),
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

        $forms = collect($columns)
            ->filter(function ($v) {
                return $v['writable'];
            })
            ->map(function ($v) use ($model) {
                if ('password' == $v['form_type']) {
                    $form = <<<EOT
          <form-item label="{$v['comment']}" prop="{{ \${$model}->id ? '' : '{$v['name']}' }}">
            <i-input type="password" v-model="formValidate.{$v['name']}" placeholder="{{ \${$model}->id ? '为空则不修改' : '请输入{$v['comment']}' }}" clearable></i-input>
          </form-item>
EOT;
                } elseif ('number' == $v['form_type']) {
                    $form = <<<EOT
          <form-item label="{$v['comment']}" prop="{$v['name']}">
            <input-number :min="0" v-model="formValidate.{$v['name']}" class="w-100"></input-number>
          </form-item>
EOT;
                } elseif ('file' == $v['form_type']) {
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

        $search = [
            'DummyLayout',
            'DummyComponentPrefix',
            'DummyModel',
            'DummyComment',
            'DummyComponentName',
            'DummyForms',
        ];

        $replace = [
            $module ? "{$module}." : '',
            $module ? "-{$module}" : '',
            $model,
            $comment,
            str_replace('_', '-', $table),
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
        $path = 'routes/'.($module ?: 'web').'.php';

        $this->createStub('route', $path);

        $controller = studly_case($table).'Controller';

        $content = "\nRoute::resource('{$table}', '{$controller}')->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);\n";

        $this->appendStub($path, $content);

        return $path;
    }

    /**
     * 创建 JS 文件.
     *
     * @param string $module
     * @param string $table
     * @param array  $columns
     *
     * @return string
     */
    protected function buildJs($module, $table, $columns)
    {
        $table = str_replace('_', '-', $table);

        $base = 'resources/assets/js/'.($module ?: 'views');
        $path = "{$base}/{$table}.js";
        $pathIndex = "{$base}/index.js";

        $forms = collect($columns)
            ->filter(function ($v) {
                return $v['writable'];
            })
            ->map(function ($v) {
                if ('password' == $v['form_type']) {
                    $form = "{$v['name']}: '',";
                } else {
                    $form = "{$v['name']}: _.get(this.info, '{$v['name']}', ''),";
                }

                return $form;
            })
            ->implode("\n        ");

        $rules = collect($columns)
            ->filter(function ($v) {
                return $v['validation'];
            })
            ->map(function ($v) {
                return "{$v['name']}: {required: true, message: '请输入{$v['comment']}', trigger: 'change'},";
            })
            ->implode("\n        ");

        $search = [
            'DummyComponentPrefix',
            'DummyComponentName',
            'DummyTable',
            'DummyUrl',
            'DummyForms',
            'DummyRules',
        ];

        $replace = [
            $module ? "-{$module}" : '',
            $table,
            str_replace('-', '_', $table),
            $module ? "/{$module}" : '',
            $forms,
            $rules,
        ];

        $this->createStub('js', $path, $search, $replace);

        $this->createStub('js-index', $pathIndex);

        $this->appendStub($pathIndex, "require('./{$table}');\n");

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
     * 给相应文件追加内容.
     *
     * @param string $path
     * @param string $content
     *
     * @return void
     */
    protected function appendStub($path, $content)
    {
        $path = base_path($path);

        $stub = $this->file->get($path);

        $stub .= $content;

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
