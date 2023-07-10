/* eslint-env node, commonjs */
/* eslint-disable @typescript-eslint/no-var-requires */

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

module.exports = function (grunt) {

  const esModuleLexer = require('es-module-lexer');


  // Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    paths: {
      root: './Resources/Private/Build/',
      sources: '<%= paths.root %>Sources/',
      typescript: '<%= paths.sources %>TypeScript/',
      node_modules: 'node_modules/'
    },
    stylelint: {
      options: {
        configFile: '<%= paths.root %>/.stylelintrc',
      }
    },
    exec: {
      ts: ((process.platform === 'win32') ? 'node_modules\\.bin\\tsc.cmd' : './node_modules/.bin/tsc') + ' --project tsconfig.json',
      lintspaces: ((process.platform === 'win32') ? 'node_modules\\.bin\\lintspaces.cmd' : './node_modules/.bin/lintspaces') + ' --editorconfig ../.editorconfig "../typo3/sysext/*/Resources/Private/**/*.html"',
      'npm-install': 'npm install'
    },
    eslint: {
      options: {
        cache: true,
        cacheLocation: './.cache/eslintcache/',
        overrideConfigFile: '.eslintrc.json'
      },
      files: {
        src: [
          '<%= paths.typescript %>/**/*.ts',
          './types/**/*.ts'
        ]
      }
    },
    watch: {
      options: {
        livereload: true
      },
      ts: {
        files: '<%= paths.typescript %>/**/*.ts',
        tasks: ['scripts', 'bell']
      }
    },
    copy: {
      options: {
        punctuation: ''
      },
      ts_files: {
        options: {
          process: (source, srcpath) => {
            const [imports] = esModuleLexer.parse(source, srcpath);

            source = require('./util/map-import.js').mapImports(source, srcpath, imports);

            // Workaround for https://github.com/microsoft/TypeScript/issues/35802 to avoid
            // rollup from complaining in karma/jsunit test setup:
            //   The 'this' keyword is equivalent to 'undefined' at the top level of an ES module, and has been rewritten
            source = source.replace('__decorate=this&&this.__decorate||function', '__decorate=function');

            return source;
          }
        },
        files: [{
          expand: true,
          cwd: '<%= paths.root %>Build/JavaScript/',
          src: ['**/*.js', '**/*.js.map'],
          dest: '<%= paths.sysext %>',
          rename: (dest, src) => dest + src
            .replace('/', '/Resources/Public/JavaScript/')
            .replace('/Resources/Public/JavaScript/tests/', '/Tests/JavaScript/')
        }]
      },
      lit: {
        options: {
          process: (content) => content.replace(/\/\/# sourceMappingURL=[^ ]+/, '')
        },
        files: [{
          expand: true,
          cwd: '<%= paths.node_modules %>',
          dest: '<%= paths.core %>Public/JavaScript/Contrib/',
          src: [
            'lit/*.js',
            'lit/decorators/*.js',
            'lit/directives/*.js',
            'lit-html/*.js',
            'lit-html/directives/*.js',
            'lit-element/*.js',
            'lit-element/decorators/*.js',
            '@lit/reactive-element/*.js',
            '@lit/reactive-element/decorators/*.js',
          ],
        }]
      }
    },
    clean: {
      lit: {
        options: {
          'force': true
        },
        src: [
          '<%= paths.core %>Public/JavaScript/Contrib/lit',
          '<%= paths.core %>Public/JavaScript/Contrib/lit-html',
          '<%= paths.core %>Public/JavaScript/Contrib/lit-element',
          '<%= paths.core %>Public/JavaScript/Contrib/@lit/reactive-element',
        ]
      }
    },
    newer: {
      options: {
        cache: './.cache/grunt-newer/'
      }
    },
    rollup: {
      options: {
        format: 'esm',
        entryFileNames: '[name].js'
      },
      'd3-selection': {
        options: {
          preserveModules: false,
          plugins: () => [
            {
              name: 'terser',
              renderChunk: code => require('terser').minify(code, grunt.config.get('terser.options'))
            }
          ]
        },
        files: {
          '<%= paths.core %>Public/JavaScript/Contrib/d3-selection.js': [
            'node_modules/d3-selection/src/index.js'
          ]
        }
      },
      'd3-dispatch': {
        options: {
          preserveModules: false,
          plugins: () => [
            {
              name: 'terser',
              renderChunk: code => require('terser').minify(code, grunt.config.get('terser.options'))
            }
          ]
        },
        files: {
          '<%= paths.core %>Public/JavaScript/Contrib/d3-dispatch.js': [
            'node_modules/d3-dispatch/src/index.js'
          ]
        }
      },
      'd3-drag': {
        options: {
          preserveModules: false,
          plugins: () => [
            {
              name: 'terser',
              renderChunk: code => require('terser').minify(code, grunt.config.get('terser.options'))
            },
            {
              name: 'externals',
              resolveId: (source) => {
                if (source === 'd3-selection') {
                  return { id: 'd3-selection', external: true }
                }
                if (source === 'd3-dispatch') {
                  return { id: 'd3-dispatch', external: true }
                }
                return null
              }
            }
          ]
        },
        files: {
          '<%= paths.core %>Public/JavaScript/Contrib/d3-drag.js': [
            'node_modules/d3-drag/src/index.js'
          ]
        }
      },
      'bootstrap': {
        options: {
          preserveModules: false,
          plugins: () => [
            {
              name: 'terser',
              renderChunk: code => require('terser').minify(code, grunt.config.get('terser.options'))
            },
            {
              name: 'externals',
              resolveId: (source) => {
                if (source === 'jquery') {
                  return { id: 'jquery', external: true }
                }
                if (source === 'bootstrap') {
                  return { id: 'node_modules/bootstrap/dist/js/bootstrap.esm.js' }
                }
                if (source === '@popperjs/core') {
                  return { id: 'node_modules/@popperjs/core/dist/esm/index.js' }
                }
                return null
              }
            }
          ]
        },
        files: {
          '<%= paths.core %>Public/JavaScript/Contrib/bootstrap.js': [
            'Sources/JavaScript/core/Resources/Public/JavaScript/Contrib/bootstrap.js'
          ]
        }
      }
    },
    npmcopy: {
      options: {
        clean: false,
        report: false,
        srcPrefix: 'node_modules/'
      },
      backend: {
        options: {
          destPrefix: '<%= paths.backend %>Public',
          copyOptions: {
            process: (source, srcpath) => {
              if (srcpath.match(/.*\.js$/)) {
                return require('./util/cjs-to-esm.js').cjsToEsm(source);
              }

              return source;
            }
          }
        },
        files: {
          'JavaScript/Contrib/mark.js': 'mark.js/dist/mark.es6.min.js'
        }
      },
      dashboardToEs6: {
        options: {
          destPrefix: '<%= paths.dashboard %>Public',
          copyOptions: {
            process: (source, srcpath) => {
              if (srcpath.match(/.*\.js$/)) {
                return require('./util/cjs-to-esm.js').cjsToEsm(source);
              }

              return source;
            }
          }
        },
        files: {
          'JavaScript/Contrib/muuri.js': 'muuri/dist/muuri.min.js'
        }
      },
      umdToEs6: {
        options: {
          destPrefix: '<%= paths.core %>Public/JavaScript/Contrib',
          copyOptions: {
            process: (source, srcpath) => {
              let imports = [], prefix = '';

              if (srcpath === 'node_modules/@claviska/jquery-minicolors/jquery.minicolors.min.js') {
                imports.push('jquery');
              }

              if (srcpath === 'node_modules/tablesort/dist/sorts/tablesort.dotsep.min.js') {
                prefix = 'import Tablesort from "tablesort";';
              }

              if (srcpath === 'node_modules/tablesort/dist/sorts/tablesort.number.min.js') {
                prefix = 'import Tablesort from "tablesort";';
              }

              return require('./util/cjs-to-esm.js').cjsToEsm(source, imports, prefix);
            }
          }
        },
        files: {
          'broadcastchannel.js': 'broadcastchannel-polyfill/index.js',
          'flatpickr/flatpickr.min.js': 'flatpickr/dist/flatpickr.js',
          'flatpickr/locales.js': 'flatpickr/dist/l10n/index.js',
          'interact.js': 'interactjs/dist/interact.min.js',
          'jquery.js': 'jquery/dist/jquery.js',
          'jquery/minicolors.js': '../node_modules/@claviska/jquery-minicolors/jquery.minicolors.min.js',
          'nprogress.js': 'nprogress/nprogress.js',
          'sortablejs.js': 'sortablejs/dist/sortable.umd.js',
          'tablesort.js': 'tablesort/dist/tablesort.min.js',
          'tablesort.dotsep.js': 'tablesort/dist/sorts/tablesort.dotsep.min.js',
          'tablesort.number.js': 'tablesort/dist/sorts/tablesort.number.min.js',
          'taboverride.js': 'taboverride/build/output/taboverride.js',
        }
      },
      install: {
        options: {
          destPrefix: '<%= paths.install %>Public/JavaScript',
          copyOptions: {
            process: (source, srcpath) => {
              if (srcpath === 'node_modules/chosen-js/chosen.jquery.js') {
                source = 'import jQuery from \'jquery\';\n' + source;
              }

              return source;
            }
          }
        },
        files: {
          'chosen.jquery.min.js': 'chosen-js/chosen.jquery.js',
        }
      },
      jqueryUi: {
        options: {
          destPrefix: '<%= paths.core %>Public/JavaScript/Contrib',
          copyOptions: {
            process: (source, srcpath) => {
              const imports = {
                'data': ['version'],
                'disable-selection': ['version'],
                'ie': ['version'],
                'plugin': ['version'],
                'position': ['version'],
                'safe-active-element': ['version'],
                'safe-blur': ['version'],
                'scroll-parent': ['version'],
                'widget': ['version'],
                'widgets/draggable': ['widgets/mouse', 'data', 'plugin', 'safe-active-element', 'safe-blur', 'scroll-parent', 'version', 'widget'],
                'widgets/droppable': ['widgets/draggable', 'widgets/mouse', 'version', 'widget'],
                'widgets/mouse': ['ie', 'version', 'widget'],
                'widgets/resizable': ['core', 'mouse', 'widget'],
                'widgets/selectable': ['core', 'mouse', 'widget'],
                'widgets/sortable': ['core', 'mouse', 'widget'],
                // just required by deprecated `core.js`
                'focusable': ['version'],
                'form': ['version'],
                'keycode': ['version'],
                'labels': ['version'],
                'jquery-patch': ['version'],
                'tabbable': ['version', 'focusable'],
                'unique-id': ['version'],
              };

              const moduleName = require('path')
                .relative('node_modules/jquery-ui/ui/', srcpath)
                .replace(/\.js$/, '');

              const code = [
                "import jQuery from 'jquery';",
              ];

              if (moduleName in imports) {
                imports[moduleName].forEach(importName => {
                  code.push("import 'jquery-ui/" + importName + ".js';");
                });
              }

              code.push('let define = null;');
              code.push(source);

              return code.join('\n');
            }
          }
        },
        files: {
          'jquery-ui/data.js': 'jquery-ui/ui/data.js',
          'jquery-ui/disable-selection.js': 'jquery-ui/ui/disable-selection.js',
          'jquery-ui/ie.js': 'jquery-ui/ui/ie.js',
          'jquery-ui/plugin.js': 'jquery-ui/ui/plugin.js',
          'jquery-ui/position.js': 'jquery-ui/ui/position.js',
          'jquery-ui/safe-active-element.js': 'jquery-ui/ui/safe-active-element.js',
          'jquery-ui/safe-blur.js': 'jquery-ui/ui/safe-blur.js',
          'jquery-ui/scroll-parent.js': 'jquery-ui/ui/scroll-parent.js',
          'jquery-ui/widget.js': 'jquery-ui/ui/widget.js',
          'jquery-ui/version.js': 'jquery-ui/ui/version.js',
          'jquery-ui/widgets/mouse.js': 'jquery-ui/ui/widgets/mouse.js',
          'jquery-ui/widgets/draggable.js': 'jquery-ui/ui/widgets/draggable.js',
          'jquery-ui/widgets/droppable.js': 'jquery-ui/ui/widgets/droppable.js',
          'jquery-ui/widgets/resizable.js': 'jquery-ui/ui/widgets/resizable.js',
          'jquery-ui/widgets/selectable.js': 'jquery-ui/ui/widgets/selectable.js',
          'jquery-ui/widgets/sortable.js': 'jquery-ui/ui/widgets/sortable.js',
          // just required by deprecated `core.js`
          'jquery-ui/focusable.js': 'jquery-ui/ui/focusable.js',
          'jquery-ui/form.js': 'jquery-ui/ui/form.js',
          'jquery-ui/keycode.js': 'jquery-ui/ui/keycode.js',
          'jquery-ui/labels.js': 'jquery-ui/ui/labels.js',
          'jquery-ui/jquery-patch.js': 'jquery-ui/ui/jquery-patch.js',
          'jquery-ui/tabbable.js': 'jquery-ui/ui/tabbable.js',
          'jquery-ui/unique-id.js': 'jquery-ui/ui/unique-id.js',
          // static legacy modules for backward compatibility
          'jquery-ui/core.js': '../Sources/JavaScript/jquery-ui/core.js',
          'jquery-ui/draggable.js': '../Sources/JavaScript/jquery-ui/draggable.js',
          'jquery-ui/droppable.js': '../Sources/JavaScript/jquery-ui/droppable.js',
          'jquery-ui/mouse.js': '../Sources/JavaScript/jquery-ui/mouse.js',
          'jquery-ui/resizable.js': '../Sources/JavaScript/jquery-ui/resizable.js',
          'jquery-ui/selectable.js': '../Sources/JavaScript/jquery-ui/selectable.js',
          'jquery-ui/sortable.js': '../Sources/JavaScript/jquery-ui/sortable.js',
        }
      },
      all: {
        options: {
          destPrefix: '<%= paths.core %>Public/JavaScript/Contrib'
        },
        files: {
          'autosize.js': 'autosize/dist/autosize.esm.js',
          'require.js': 'requirejs/require.js',
          'cropperjs.js': 'cropperjs/dist/cropper.esm.js',
          'es-module-shims.js': 'es-module-shims/dist/es-module-shims.js',
          'luxon.js': 'luxon/build/es6/luxon.js',
          '../../../../../backend/Resources/Public/Images/colorpicker/jquery.minicolors.png': '../node_modules/@claviska/jquery-minicolors/jquery.minicolors.png',
        }
      }
    },
    terser: {
      options: {
        output: {
          ecma: 8
        }
      },
      thirdparty: {
        files: {
          '<%= paths.core %>Public/JavaScript/Contrib/es-module-shims.js': ['<%= paths.core %>Public/JavaScript/Contrib/es-module-shims.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/broadcastchannel.js': ['<%= paths.core %>Public/JavaScript/Contrib/broadcastchannel.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/cropperjs.js': ['<%= paths.core %>Public/JavaScript/Contrib/cropperjs.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/flatpickr/flatpickr.min.js': ['<%= paths.core %>Public/JavaScript/Contrib/flatpickr/flatpickr.min.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/flatpickr/locales.js': ['<%= paths.core %>Public/JavaScript/Contrib/flatpickr/locales.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/luxon.js': ['<%= paths.core %>Public/JavaScript/Contrib/luxon.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/require.js': ['<%= paths.core %>Public/JavaScript/Contrib/require.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/nprogress.js': ['<%= paths.core %>Public/JavaScript/Contrib/nprogress.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/taboverride.js': ['<%= paths.core %>Public/JavaScript/Contrib/taboverride.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/core.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/core.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/data.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/data.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/disable-selection.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/disable-selection.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/draggable.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/draggable.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/droppable.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/droppable.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/focusable.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/focusable.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/form.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/form.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/ie.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/ie.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/jquery-patch.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/jquery-patch.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/keycode.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/keycode.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/labels.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/labels.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/mouse.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/mouse.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/plugin.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/plugin.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/position.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/position.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/resizable.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/resizable.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/safe-active-element.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/safe-active-element.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/safe-blur.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/safe-blur.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/scroll-parent.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/scroll-parent.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/selectable.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/selectable.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/sortable.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/sortable.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/tabbable.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/tabbable.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/unique-id.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/unique-id.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/version.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/version.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widget.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widget.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widgets/draggable.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widgets/draggable.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widgets/droppable.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widgets/droppable.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widgets/mouse.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widgets/mouse.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widgets/resizable.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widgets/resizable.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widgets/selectable.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widgets/selectable.js'],
          '<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widgets/sortable.js': ['<%= paths.core %>Public/JavaScript/Contrib/jquery-ui/widgets/sortable.js'],
          '<%= paths.install %>Public/JavaScript/chosen.jquery.min.js': ['<%= paths.install %>Public/JavaScript/chosen.jquery.min.js']
        }
      },
      typescript: {
        options: {
          output: {
            preamble: '/*\n' +
              ' * This file is part of the TYPO3 CMS project.\n' +
              ' *\n' +
              ' * It is free software; you can redistribute it and/or modify it under\n' +
              ' * the terms of the GNU General Public License, either version 2\n' +
              ' * of the License, or any later version.\n' +
              ' *\n' +
              ' * For the full copyright and license information, please read the\n' +
              ' * LICENSE.txt file that was distributed with this source code.\n' +
              ' *\n' +
              ' * The TYPO3 project - inspiring people to share!' +
              '\n' +
              ' */',
            comments: /^!/
          }
        },
        files: [
          {
            expand: true,
            src: [
              '<%= paths.root %>Build/JavaScript/**/*.js',
            ],
            dest: '<%= paths.root %>Build',
            cwd: '.',
          }
        ]
      }
    },
    concurrent: {
      npmcopy: ['npmcopy:backend', 'npmcopy:umdToEs6', 'npmcopy:jqueryUi', 'npmcopy:install', 'npmcopy:all'],
      lint: ['eslint', 'stylelint', 'exec:lintspaces'],
      compile_assets: ['scripts', 'css'],
      compile_flags: ['flags-build'],
      minify_assets: ['terser:thirdparty', 'terser:t3editor'],
      copy_static: ['copy:core_icons', 'copy:install_icons', 'copy:module_icons', 'copy:extension_icons', 'copy:fonts', 'copy:lit', 'copy:t3editor'],
      build: ['copy:core_icons', 'copy:install_icons', 'copy:module_icons', 'copy:extension_icons', 'copy:fonts', 'copy:t3editor'],
    },
  });

  // Register tasks
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-rollup');
  grunt.loadNpmTasks('grunt-npmcopy');
  grunt.loadNpmTasks('grunt-terser');
  grunt.loadNpmTasks('@lodder/grunt-postcss');
  grunt.loadNpmTasks('grunt-contrib-copy');
  grunt.loadNpmTasks('grunt-contrib-clean');
  grunt.loadNpmTasks('grunt-exec');
  grunt.loadNpmTasks('grunt-eslint');
  grunt.loadNpmTasks('grunt-stylelint');
  grunt.loadNpmTasks('grunt-newer');
  grunt.loadNpmTasks('grunt-concurrent');

  /**
   * grunt lint
   *
   * call "$ grunt lint"
   *
   * this task does the following things:
   * - eslint
   * - stylelint
   * - lintspaces
   */
  grunt.registerTask('lint', ['concurrent:lint']);



  /**
   * grunt update task
   *
   * call "$ grunt update"
   *
   * this task does the following things:
   * - copy some components to a specific destinations because they need to be included via PHP
   */
  grunt.registerTask('update', ['rollup', 'exec:ckeditor', 'concurrent:npmcopy']);

  /**
   * grunt compile-typescript task
   *
   * call "$ grunt compile-typescript"
   *
   * This task does the following things:
   * - 1) Check all TypeScript files (*.ts) with ESLint which are located in sysext/<EXTKEY>/Resources/Private/TypeScript/*.ts
   * - 2) Compiles all TypeScript files (*.ts) which are located in sysext/<EXTKEY>/Resources/Private/TypeScript/*.ts
   */
  grunt.registerTask('compile-typescript', ['tsconfig', 'eslint', 'exec:ts']);

  /**
   * grunt scripts task
   *
   * call "$ grunt scripts"
   *
   * this task does the following things:
   * - 1) Compiles TypeScript (see compile-typescript)
   * - 2) Copy all generated JavaScript files to public folders
   * - 3) Minify build
   */
  grunt.registerTask('scripts', ['compile-typescript', 'newer:terser:typescript', 'newer:copy:ts_files']);

  /**
   * grunt clear-build task
   *
   * call "$ grunt clear-build"
   *
   * Removes all build-related assets, e.g. cache and built files
   */
  grunt.registerTask('clear-build', function () {
    grunt.option('force');
    grunt.file.delete('.cache');
    grunt.file.delete('JavaScript');
  });

  /**
   * grunt tsconfig task
   *
   * call "$ grunt tsconfig"
   *
   * this task updates the tsconfig.json file with modules paths for all sysexts
   */
  grunt.task.registerTask('tsconfig', function () {
    const config = grunt.file.readJSON('tsconfig.json');
    const typescriptPath = grunt.config.get('paths.typescript');
    config.compilerOptions.paths = {};
    grunt.file.expand(typescriptPath + '*/').map(dir => dir.replace(typescriptPath, '')).forEach((path) => {
      const extname = path.match(/^([^/]+?)\//)[1].replace(/_/g, '-')
      config.compilerOptions.paths['@typo3/' + extname + '/*'] = [path + '*'];
    });

    grunt.file.write('tsconfig.json', JSON.stringify(config, null, 4) + '\n');
  });

  /**
   * Outputs a "bell" character. When output, modern terminals flash shortly or produce a notification (usually configurable).
   * This Grunt config uses it after the "watch" task finished compiling, signaling to the developer that her/his changes
   * are now compiled.
   */
  grunt.registerTask('bell', () => console.log('\u0007'));


  /**
   * grunt build task (legacy, for those used to it). Use `grunt default` instead.
   *
   * call "$ grunt build"
   *
   * this task does the following things:
   * - execute exec:npm-install task
   * - execute all task
   */
  grunt.registerTask('build', ['exec:npm-install', 'default']);
};
