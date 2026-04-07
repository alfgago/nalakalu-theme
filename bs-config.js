module.exports = {
  proxy: "https://nalakalu.stag.host",
  files: [
    "templates/**/*.php",
    "*.php", 
    "assets/css/*.css",
    "assets/js/*.js"
  ],
  port: 3000,
  open: "external",
  notify: false,
  reloadDelay: 100,
  reloadDebounce: 100,
  reloadThrottle: 100,
  snippetOptions: {
    ignorePaths: ["wp-admin/**"]
  },
  https: true,
  middleware: false
};