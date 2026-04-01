/** @type {import('vite').UserConfig} */
export default {
    build: {
        assetsDir: '',
        rollupOptions: {
            input: ['resources/js/ai-trace.js', 'resources/css/ai-trace.css'],
            output: {
                assetFileNames: '[name][extname]',
                entryFileNames: '[name].js',
            },
        },
        outDir: 'dist',
    },
};
