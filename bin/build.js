import esbuild from 'esbuild'

const isDev = process.argv.includes('--dev')

async function compile(options) {
    const context = await esbuild.context(options)

    if (isDev) {
        await context.watch()
    } else {
        await context.rebuild()
        await context.dispose()
    }
}

compile({
    entryPoints: ['./resources/js/advanced-search.js'],
    outfile: './resources/dist/components/advanced-search.js',
    bundle: true,
    minify: !isDev,
    format: 'esm',
    platform: 'neutral',
    target: ['chrome111', 'firefox113', 'safari16.4'],
    logLevel: 'info',
})

compile({
    entryPoints: ['./resources/css/filament-dynamic-tables.css'],
    outfile: './resources/dist/filament-dynamic-tables.css',
    bundle: true,
    minify: !isDev,
    target: ['chrome111', 'firefox113', 'safari16.4'],
    logLevel: 'info',
}).then(() => {
    console.log('Built resources/dist/filament-dynamic-tables.css and the Alpine components')
})
