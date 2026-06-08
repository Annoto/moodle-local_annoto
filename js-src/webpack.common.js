const path = require('path');
const webpack = require('webpack');
const packageData = require('./package.json');

module.exports = function (env) {
    return {
        entry: './src/main.ts',
        target: 'web',
        output: {
            filename: 'annoto.js',
            // Build directly into the Moodle plugin's locally-served js/ folder
            // (one level up from js-src/) so the bundle ships inside the plugin
            // instead of being loaded from the Annoto CDN.
            path: path.resolve(__dirname, '../js/'),
            library: 'AnnotoMoodle',
            libraryTarget: 'umd',
            sourceMapFilename: 'annoto.map',
            clean: true,
            publicPath: '',
        },
        module: {
            rules: [
                {
                    test: /\.ts$/,
                    loader: 'ts-loader',
                    options: {
                        configFile: 'tsconfig.json',
                    },
                    exclude: /node_modules/,
                },
            ],
        },
        resolve: {
            extensions: ['.ts', '.js'],
        },
        plugins: [
            new webpack.DefinePlugin({
                'process.env': {
                    version: JSON.stringify(packageData.version),
                    ENV: JSON.stringify(env.envName),
                    name: JSON.stringify(packageData.name),
                },
            }),
        ],
    };
};
