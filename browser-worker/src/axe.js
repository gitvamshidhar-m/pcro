import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const axePath = require.resolve('axe-core/axe.min.js');

const MAX_VIOLATIONS = 25;

export async function runAxe(page) {
    try {
        await page.addScriptTag({ path: axePath, type: 'text/javascript' });

        return await page.evaluate(async (maxViolations) => {
            const results = await window.axe.run(document, {
                runOnly: {
                    type: 'tag',
                    values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'],
                },
                resultTypes: ['violations'],
            });

            const violations = results.violations.slice(0, maxViolations).map((violation) => ({
                id: violation.id,
                impact: violation.impact,
                help: violation.help,
                description: violation.description,
                help_url: violation.helpUrl,
                tags: violation.tags.slice(0, 8),
                node_count: violation.nodes.length,
                samples: violation.nodes.slice(0, 3).map((node) => ({
                    target: node.target,
                    summary: String(node.failureSummary ?? '').slice(0, 500),
                })),
            }));

            return {
                available: true,
                violations: violations.length,
                critical: results.violations.filter((violation) => violation.impact === 'critical').length,
                serious: results.violations.filter((violation) => violation.impact === 'serious').length,
                moderate: results.violations.filter((violation) => violation.impact === 'moderate').length,
                minor: results.violations.filter((violation) => violation.impact === 'minor').length,
                total_nodes: results.violations.reduce((total, violation) => total + violation.nodes.length, 0),
                items: violations,
            };
        }, MAX_VIOLATIONS);
    } catch (error) {
        return {
            available: false,
            violations: null,
            items: [],
            error: error.message,
        };
    }
}
