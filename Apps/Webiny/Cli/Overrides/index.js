const Plugin = require('webiny-cli/lib/plugin');

class Overrides extends Plugin {
    constructor(program) {
        super(program);

        // We need to override webpack `MultiCompiler` to force build execution in a chain of promises instead of parallel execution.
        const Module = require('module');
        if (!Module.prototype.__isOverridden) {
            Module.prototype.__isOverridden = true;
            const originalRequire = Module.prototype.require;
            Module.prototype.require = function () {
                // Override MultiCompiler to force chained builds instead of parallel
                if (arguments[0].includes('MultiCompiler')) {
                    return originalRequire.apply(this, [__dirname + '/MultiCompiler']);
                }
                return originalRequire.apply(this, arguments);
            };
        }
    }
}

Overrides.task = 'overrides';

module.exports = Overrides;