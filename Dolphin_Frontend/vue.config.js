const webpack = require("webpack");
// Default to local backend if env var isn't set
const API_BASE_URL =
  process.env.VUE_APP_API_BASE_URL || "http://127.0.0.1:8000";

module.exports = {
  configureWebpack: {
    plugins: [
      new webpack.DefinePlugin({
        __VUE_OPTIONS_API__: true,
        __VUE_PROD_DEVTOOLS__: false,
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false,
      }),
    ],
  },
  devServer: {
    host: "127.0.0.1",
    port: 8080,
    proxy: {
      "/api": {
        target: API_BASE_URL,
        changeOrigin: true,
        pathRewrite: { "^/api": "/api" },
      },
    },
  },
};
