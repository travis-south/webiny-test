/**
 === NOTES ===
 - in development mode your bundles will be significantly larger in size due to hot-reload code being appended to them
 */
const path = require('path');
const webpack = require('webpack');
const ExtractTextPlugin = require('extract-text-webpack-plugin');
const Visualizer = require('webpack-visualizer-plugin');
const _ = require('lodash');
const AssetsPlugin = require('./plugins/Assets');
const ModuleIdsPlugin = require('./plugins/ModuleIds');
const ChunkIdsPlugin = require('./plugins/ChunkIds');
const Webiny = require('webiny-cli/lib/webiny');

module.exports = function (app) {
    // Construct URL for hot-reload and assets
    const port = _.get(Webiny.getConfig(), 'cli.port', 3000);
    const domain = _.get(Webiny.getConfig(), 'cli.domain', 'http://localhost');
    const url = domain + ':' + port;

    const name = app.getName();
    const context = Webiny.projectRoot(app.getSourceDir());
    const outputPath = path.resolve(Webiny.projectRoot(), 'public_html/build/development', app.getPath());
    const publicPath = url + '/build/development/' + app.getPath() + '/';

    const plugins = [
        new webpack.NamedModulesPlugin(),
        new ModuleIdsPlugin(),
        new ChunkIdsPlugin(),
        new webpack.NoEmitOnErrorsPlugin(),
        new webpack.DefinePlugin({
            'DEVELOPMENT': true,
            'PRODUCTION': false,
            'process.env': {
                'NODE_ENV': JSON.stringify(process.env.NODE_ENV)
            }
        }),
        new webpack.HotModuleReplacementPlugin(),
        new ExtractTextPlugin('app.css'),
        // Generate meta.json to use for app bootstrap based on generated assets
        new AssetsPlugin({
            manifestVariable: 'window["webinyConfig"]["Meta"]["' + name + '"].chunks'
        }),
        new Visualizer({filename: 'stats.html'})
    ];

    // Check if app has vendor DLL defined
    const dllPath = path.resolve(Webiny.projectRoot(), 'public_html/build/development', app.getPath(), 'vendor.manifest.json');
    if (Webiny.fileExists(dllPath)) {
        plugins.push(
            new webpack.DllReferencePlugin({
                context,
                manifest: require(dllPath)
            })
        );
    }

    if (name !== 'Webiny.Core') {
        plugins.push(
            new webpack.DllReferencePlugin({
                context: Webiny.projectRoot('Apps/Webiny/Js/Core'),
                manifest: Webiny.projectRoot('public_html/build/development') + '/Webiny_Core/vendor.manifest.json'
            })
        );
    }

    const fileExtensionRegex = /\.(png|jpg|gif|jpeg|mp4|mp3|woff2?|ttf|otf|eot|svg|ico)$/;

    return {
        name,
        cache: true,
        context,
        entry: {
            app: [
                'react-hot-loader/patch',
                'webpack-hot-middleware/client?name=' + name + '&path=' + url + '/__webpack_hmr&quiet=false&noInfo=true&warn=false&overlay=true&reload=false',
                'webpack/hot/only-dev-server',
                './App.js'
            ]
        },
        output: {
            path: outputPath,
            filename: '[name].js',
            chunkFilename: 'chunks/[name].js',
            jsonpFunction: 'webpackJsonp' + app.getName().replace('.', ''),
            publicPath
        },
        plugins,
        externals: name === 'Webiny.Core' ? {} : require('./externals'),
        module: {
            rules: [
                {
                    test: /\.(js|jsx)$/,
                    exclude: /node_modules/,
                    use: [
                        {
                            loader: 'babel-loader',
                            options: {
                                presets: [
                                    [require.resolve('babel-preset-es2015')],
                                    require.resolve('babel-preset-react')
                                ],
                                plugins: [
                                    require.resolve('react-hot-loader/babel'),
                                    require.resolve('babel-plugin-transform-async-to-generator'),
                                    [require.resolve('babel-plugin-transform-object-rest-spread'), {'useBuiltIns': true}],
                                    [require.resolve('babel-plugin-syntax-dynamic-import')],
                                    [require.resolve('babel-plugin-lodash')],
                                    [require.resolve('babel-plugin-transform-builtin-extend'), {globals: ['Error']}],
                                    // This plugin is required to force all css/scss imports to have a resourceQuery
                                    [require.resolve('babel-plugin-transform-rename-import'), {
                                        original: '^(.*?\.s?css)$', replacement: '$1?',
                                    }]
                                ]
                            }
                        },
                        'hot-accept-loader'
                    ]
                },
                {
                    test: /\.(js|jsx)$/,
                    exclude: /node_modules/,
                    include: Webiny.projectRoot(),
                    use: 'i18n-loader'
                },
                require('./styles')(app),
                {
                    test: /node_modules/,
                    include: fileExtensionRegex,
                    loader: 'file-loader',
                    options: {
                        context: path.resolve(Webiny.projectRoot(), 'Apps', app.getAppName(), 'node_modules'),
                        name: 'external/[path][name].[ext]'
                    }
                },
                {
                    test: fileExtensionRegex,
                    exclude: /node_modules/,
                    loader: 'file-loader',
                    options: {
                        context: path.resolve(Webiny.projectRoot(), app.getSourceDir(), 'Assets'),
                        name: '[path][name].[ext]',
                        outputPath: (file) => {
                            if (file.startsWith('_/')) {
                                const parts = file.replace(/_\//g, '').split('/Assets/');
                                file = path.join('external', parts[0], parts[1]);
                            }
                            return file;
                        }
                    }
                }
            ]
        },
        resolve: require('./resolve')(app),
        resolveLoader: {
            modules: [
                __dirname + '/loaders', 'node_modules',
                path.resolve(Webiny.projectRoot(), 'Apps/Webiny/node_modules'),
                path.resolve(Webiny.projectRoot(), 'Apps/' + app.getAppName() + '/node_modules')
            ]
        }
    }
};
