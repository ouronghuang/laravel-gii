<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Laravel Gii</title>
  <link href="https://cdn.bootcss.com/twitter-bootstrap/4.4.1/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.bootcss.com/iview/3.5.4/styles/iview.css" rel="stylesheet">
  <style>
    ::-webkit-scrollbar {
      width: 0;
      height: 10px;
    }

    [v-cloak] {
      display: none;
    }

    label {
      margin-bottom: 0;
    }

    .ivu-back-top i {
      padding: 4px;
    }

    hr {
      border-top: 1px dashed #eee;
      margin: .5rem 0;
    }
  </style>
</head>
<body>
  <div id="app" v-cloak>
    <h1 class="text-center my-5">
      Laravel Gii
    </h1>
    <div class="container">
      <i-form ref="formValidate" :model="formValidate" :rules="ruleValidate" label-position="top">
        <div class="row">
          <div class="col">
            <form-item label="模块名">
              <i-input v-model="formValidate.module" placeholder="可不填" clearable></i-input>
            </form-item>
          </div>
          <div class="col">
            <form-item label="表名" prop="table">
              <i-input v-model="formValidate.table" placeholder="请输入表名" clearable></i-input>
            </form-item>
          </div>
        </div>
        <div>
          <div class="row align-items-center" v-for="(v, k) in formValidate.columns" :key="k">
            <div class="col">
              <form-item
                label="字段名"
                :prop="`columns.${k}.name`"
                :rules="{required: true, message: '请输入字段名', trigger: 'change'}"
              >
                <i-input v-model="v.name" placeholder="请输入字段名" clearable></i-input>
              </form-item>
            </div>
            <div class="col">
              <form-item
                label="类型"
                :prop="`columns.${k}.type`"
                :rules="{required: true, message: '请选择类型', trigger: 'change'}"
              >
                <i-select placeholder="请选择类型" v-model="v.type">
                  <i-option :value="type" v-for="type in columnTypes" :key="type">
                    @{{ type }}
                  </i-option>
                </i-select>
              </form-item>
            </div>
            <div class="col-auto">
              <form-item label="唯一" title="表单验证 (Request)">
                <i-switch v-model="v.unique">
                  <span slot="open">是</span>
                  <span slot="close">否</span>
                </i-switch>
              </form-item>
            </div>
            <div class="col-auto">
              <form-item label="可空">
                <i-switch v-model="v.nullable">
                  <span slot="open">是</span>
                  <span slot="close">否</span>
                </i-switch>
              </form-item>
            </div>
            <div class="col-auto">
              <form-item label="可写" title="模型 (Model)">
                <i-switch v-model="v.writable">
                  <span slot="open">是</span>
                  <span slot="close">否</span>
                </i-switch>
              </form-item>
            </div>
            <div class="col-auto">
              <form-item label="可读" title="资源 (Resource)">
                <i-switch v-model="v.readable">
                  <span slot="open">是</span>
                  <span slot="close">否</span>
                </i-switch>
              </form-item>
            </div>
            <div class="col-auto">
              <form-item label="密码" title="表单验证 (Request) | 控制器 (Controller)">
                <i-switch v-model="v.password">
                  <span slot="open">是</span>
                  <span slot="close">否</span>
                </i-switch>
              </form-item>
            </div>
            <div class="col">
              <form-item label="默认">
                <i-input v-model="v.default" placeholder="可不填" clearable></i-input>
              </form-item>
            </div>
            <div class="col">
              <form-item label="注释">
                <i-input v-model="v.comment" placeholder="可不填" clearable></i-input>
              </form-item>
            </div>
            <div class="col-auto">
              <form-item label="操作">
                <poptip
                  confirm
                  title="确认删除？"
                  @on-ok="delColumn(k)">
                  <i-button type="error">
                    删除
                  </i-button>
                </poptip>
              </form-item>
            </div>
          </div>
          <form-item>
            <i-button type="dashed" long @click="addColumn">
              新增字段
            </i-button>
          </form-item>
        </div>
        <form-item>
          <i-button type="primary" long @click="handleSubmit" :loading="loading">
            提交
          </i-button>
        </form-item>
      </i-form>
    </div>
    <div>
      <back-top :bottom="8" :right="8"/>
    </div>
  </div>
  <script src="https://cdn.bootcss.com/vue/2.6.11/vue.min.js"></script>
  <script src="https://cdn.bootcss.com/iview/3.5.4/iview.min.js"></script>
  <script src="https://cdn.bootcss.com/axios/0.19.2/axios.min.js"></script>
  <script>
    const app = new Vue({
      el: '#app',
      data: {
        loading: false,
        formValidate: {
          module: 'admin',
          table: '',
          columns: []
        },
        ruleValidate: {
          table: {required: true, message: '请输入表名', trigger: 'change'}
        },
        columnTypes: [
          'string',
          'bigIncrements',
          'bigInteger',
          'binary',
          'boolean',
          'char',
          'date',
          'dateTime',
          'dateTimeTz',
          'decimal',
          'double',
          'enum',
          'float',
          'geometry',
          'geometryCollection',
          'increments',
          'integer',
          'ipAddress',
          'json',
          'jsonb',
          'lineString',
          'longText',
          'macAddress',
          'mediumIncrements',
          'mediumInteger',
          'mediumText',
          'morphs',
          'multiLineString',
          'multiPoint',
          'multiPolygon',
          'nullableMorphs',
          'point',
          'polygon',
          'smallIncrements',
          'smallInteger',
          'text',
          'time',
          'timeTz',
          'timestamp',
          'timestampTz',
          'tinyIncrements',
          'tinyInteger',
          'unsignedBigInteger',
          'unsignedDecimal',
          'unsignedInteger',
          'unsignedMediumInteger',
          'unsignedSmallInteger',
          'unsignedTinyInteger',
          'uuid',
          'year',

          'nullableTimestamps',
          'rememberToken',
          'softDeletes',
          'softDeletesTz'
        ]
      },
      mounted() {
        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.head.querySelector('meta[name="csrf-token"]').content;

        this.addColumn();
      },
      methods: {
        handleSubmit() {
          this.$refs.formValidate.validate((valid) => {
            if (valid) {
              this.loading = true;

              axios
                .post('/gii', this.formValidate)
                .then(({data}) => {

                  this.loading = false;
                })
                .catch(({response}) => {
                  this.$Message.error(response.data.message);

                  this.loading = false;
                });
            }
          });
        },
        column() {
          return {
            type: this.columnTypes[0],
            name: '',
            unique: false,
            nullable: false,
            writable: true,
            readable: true,
            password: false,
            default: '',
            comment: ''
          };
        },
        addColumn() {
          this.formValidate.columns.push(this.column());
        },
        delColumn(index) {
          if (this.formValidate.columns.length <= 1) {
            return this.$Message.error('至少有一个字段');
          }

          this.formValidate.columns.splice(index, 1);
        }
      }
    });
  </script>
</body>
</html>
