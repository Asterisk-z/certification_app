/**
 * Wrapper around spatie/browsershot's bin/browser.cjs that never waits forever
 * for Chrome to exit.
 *
 * On some Windows machines (endpoint-security drivers holding the terminating
 * process) Chrome acknowledges the CDP `Browser.close` request and releases its
 * handles, but its process object is never signalled. puppeteer's
 * `browser.close()` waits on that signal, so although the render output had
 * already been written, the Node process (and therefore the PHP job) hung until
 * Browsershot's timeout fired.
 *
 * Browsershot prints the result to stdout *before* it closes the browser, so
 * after a short grace period we force-kill the process tree, drop our
 * references to it and let Node exit normally.
 *
 * Wired in via Browsershot::setBinPath() in CertificateRenderService.
 */
const path = require('path');
const { execSync } = require('child_process');
const puppeteer = require('puppeteer');

const CLOSE_GRACE_MS = Number(process.env.BROWSERSHOT_CLOSE_GRACE_MS || 5000);

// Same API as the puppeteer module (connect, KnownDevices, ...) with a launch()
// whose browser.close() is capped.
const patched = Object.create(puppeteer);

patched.launch = async function (...launchArgs) {
    const browser = await puppeteer.launch(...launchArgs);
    const originalClose = browser.close.bind(browser);

    browser.close = async () => {
        const proc = browser.process();

        const closed = originalClose().then(() => true, () => false);
        const grace = new Promise((resolve) => setTimeout(() => resolve(false), CLOSE_GRACE_MS).unref());

        if (await Promise.race([closed, grace])) {
            return;
        }

        if (proc && proc.pid) {
            try {
                if (process.platform === 'win32') {
                    execSync(`taskkill /pid ${proc.pid} /T /F`, { stdio: 'ignore' });
                } else {
                    proc.kill('SIGKILL');
                }
            } catch (e) {
                // Already gone or not killable; nothing more we can do.
            }

            for (const stream of proc.stdio || []) {
                try { stream?.destroy(); } catch (e) { /* ignore */ }
            }
            try { proc.unref(); } catch (e) { /* ignore */ }
        }

        try { browser.disconnect(); } catch (e) { /* ignore */ }

        // Last resort if something still keeps the event loop alive: exit once
        // stdout has had time to flush. unref() keeps this from delaying a
        // normal exit.
        setTimeout(() => process.exit(0), 2000).unref();
    };

    return browser;
};

const { callChrome } = require(path.join(__dirname, '..', '..', 'vendor', 'spatie', 'browsershot', 'bin', 'browser.cjs'));

callChrome(patched);
