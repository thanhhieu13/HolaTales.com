const path = require('path');
const crypto = require('crypto');
const webpack = require('webpack');
const VueLoaderPlugin = require('vue-loader/lib/plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

// Preserve Laravel's existing mix() asset contract without the legacy Mix toolchain.
class AssetManifestPlugin {
    apply(compiler) {
        compiler.hooks.thisCompilation.tap('AssetManifestPlugin', compilation => {
            compilation.hooks.processAssets.tap({name:'AssetManifestPlugin', stage:webpack.Compilation.PROCESS_ASSETS_STAGE_SUMMARIZE}, () => {
                const manifest = {};
                for (const name of ['js/app.js', 'css/app.css']) {
                    const asset = compilation.getAsset(name);
                    if (asset) manifest['/' + name] = '/' + name + '?id=' + crypto.createHash('sha256').update(asset.source.source()).digest('hex').slice(0, 20);
                }
                compilation.emitAsset('mix-manifest.json', new webpack.sources.RawSource(JSON.stringify(manifest, null, 2)));
            });
        });
    }
}
module.exports = {
    entry: {app: ['./resources/js/app.js', './resources/sass/app.scss']},
    output: {path:path.resolve(__dirname,'public'), filename:'js/[name].js', publicPath:'/', clean:false},
    resolve: {extensions:['.js','.vue'], alias:{vue$:'vue/dist/vue.runtime.esm.js'}},
    module: {rules:[
        {test:/\.vue$/, loader:'vue-loader'},
        {test:/\.css$/, oneOf:[{resourceQuery:/vue/,use:['vue-style-loader',{loader:'css-loader',options:{esModule:false}}]},{use:[{loader:MiniCssExtractPlugin.loader,options:{esModule:false}},{loader:'css-loader',options:{esModule:false,url:{filter:url=>!url.startsWith('/')}}}]}]},
        {test:/\.s[ac]ss$/, use:[{loader:MiniCssExtractPlugin.loader,options:{esModule:false}},{loader:'css-loader',options:{esModule:false,url:{filter:url=>!url.startsWith('/')}}},{loader:'sass-loader', options:{sassOptions:{silenceDeprecations:['legacy-js-api','import','global-builtin','color-functions']}}}]},
        {test:/\.(woff2?|ttf|eot|png|jpe?g|svg|gif)$/, type:'asset/resource', generator:{filename:'assets/[name].[contenthash][ext]'}}
    ]},
    plugins:[new VueLoaderPlugin(),new MiniCssExtractPlugin({filename:'css/[name].css'}),new AssetManifestPlugin()],
    devtool:false,
    performance:{hints:false},
};
