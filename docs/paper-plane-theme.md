# Paper Plane theme validation

Paper Plane uses a brighter red failed-device marker against its dark panel
cells. Panel headers and details-dot colors remain unchanged.

## Populated browser fixtures

The tests load the actual core and plugin stylesheets. Device names,
measurements, poller totals, and alerts are synthetic. These are browser
fixtures, not a live installation. The synthetic page heading outside the
application panels uses light grey so it is readable on the dark page.

![Paper Plane dashboard with populated monitoring panels](../images/paper-plane-theme-dashboard.png)

The browser checks paragraph and heading contrast, opens a real jQuery UI details
dialog, checks its alert data, and closes it. All four status colors are also
checked against plain/odd/even rows inside the native `panel_data` wrapper
and against details titlebars. The panel stylesheet owns the dark cell background.

![Paper Plane details with synthetic warning and error rows](../images/paper-plane-theme-status.png)

## Reproduce

Use the setup in [Cacti theme validation](cacti-theme.md). Set `CACTI_ROOT` to the
adjacent core checkout and run from the plugin root:

```sh
NODE_PATH="$CACTI_ROOT/tests/e2e/node_modules" mise exec node@22 -- node \
  "$CACTI_ROOT/tests/e2e/node_modules/@playwright/test/cli.js" test \
  --config tests/e2e/playwright.config.cjs
```

Set `UPDATE_THEME_SCREENSHOTS=1` to regenerate screenshots. Contrast checks
compose alpha and sample linear-gradient stops and interiors. They do not prove
contrast for arbitrary image backgrounds or every rendered pixel.

After committing, measure changed CSS line coverage:

```sh
mise exec python@3.12 -- python "$CACTI_ROOT/tests/tools/report_changed_coverage.py" \
  --base fix/sunrise-theme-status-contrast --language css --minimum 100 \
  --browser-root tests/e2e/test-results
```

This layer extends the shared browser tests above Sunrise in the native theme
stack. Use `origin/develop` after the lower layers merge. The gate measures
executed changed CSS lines, not whole-plugin or branch coverage.
