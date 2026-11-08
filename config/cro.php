<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scoring & rule version
    |--------------------------------------------------------------------------
    |
    | Every stored score references this version so historical results remain
    | explainable after the heuristics are tuned.
    |
    */
    'version' => '2026.11.1',

    'categories' => [

        'ux' => [
            'weight' => 0.20,
            'checks' => [
                'ux.navigation_clarity' => [
                    'label' => 'Navigation is clear and shallow',
                    'weight' => 1.2,
                    'severity' => 'medium',
                    'confidence' => 88,
                    'finding' => 'Primary navigation is hard to scan or too deep',
                    'recommendation' => 'Reduce primary navigation to 4-7 outcome-focused labels and expose the contact or purchase path in the header.',
                    'opportunity' => ['impact' => 6, 'effort' => 4, 'reach' => 9, 'funnel' => 6],
                ],
                'ux.mobile_viewport' => [
                    'label' => 'Mobile viewport and responsive layout',
                    'weight' => 1.5,
                    'severity' => 'high',
                    'confidence' => 95,
                    'finding' => 'Mobile rendering signals are missing or broken',
                    'recommendation' => 'Add a responsive viewport, prevent horizontal overflow and test primary tasks at 360px width.',
                    'opportunity' => ['impact' => 8, 'effort' => 3, 'reach' => 8, 'funnel' => 7],
                ],
                'ux.readability' => [
                    'label' => 'Copy is readable',
                    'weight' => 1.0,
                    'severity' => 'low',
                    'confidence' => 70,
                    'finding' => 'Body copy is difficult to scan',
                    'recommendation' => 'Shorten sentences and paragraphs, use plain language and break dense copy into scannable sections.',
                    'opportunity' => ['impact' => 5, 'effort' => 3, 'reach' => 8, 'funnel' => 5],
                ],
                'ux.content_hierarchy' => [
                    'label' => 'Heading hierarchy is coherent',
                    'weight' => 1.2,
                    'severity' => 'medium',
                    'confidence' => 90,
                    'finding' => 'Heading structure does not communicate the page hierarchy',
                    'recommendation' => 'Use exactly one descriptive H1 per page and structure supporting sections with sequential H2/H3 headings.',
                    'opportunity' => ['impact' => 5, 'effort' => 2, 'reach' => 9, 'funnel' => 5],
                ],
                'ux.page_structure' => [
                    'label' => 'Page landmarks and structure',
                    'weight' => 0.8,
                    'severity' => 'low',
                    'confidence' => 80,
                    'finding' => 'Page landmarks and sections are incomplete',
                    'recommendation' => 'Use semantic header, main, nav and footer landmarks, and group content into clearly labelled sections.',
                    'opportunity' => ['impact' => 4, 'effort' => 3, 'reach' => 8, 'funnel' => 4],
                ],
                'ux.layout_consistency' => [
                    'label' => 'Layout is consistent and stable',
                    'weight' => 0.8,
                    'severity' => 'low',
                    'confidence' => 65,
                    'finding' => 'Layout consistency or stability issues were detected',
                    'recommendation' => 'Standardise spacing, button styles and container widths, and reserve space for late-loading media to reduce layout shift.',
                    'opportunity' => ['impact' => 4, 'effort' => 4, 'reach' => 7, 'funnel' => 4],
                ],
            ],
        ],

        'conversion' => [
            'weight' => 0.25,
            'checks' => [
                'conversion.primary_cta_present' => [
                    'label' => 'A primary call to action is present',
                    'weight' => 1.6,
                    'severity' => 'critical',
                    'confidence' => 92,
                    'finding' => 'No clear primary call to action was detected',
                    'recommendation' => 'Add one visually dominant primary CTA on every conversion-critical page, phrased as the next step the visitor should take.',
                    'opportunity' => ['impact' => 10, 'effort' => 3, 'reach' => 9, 'funnel' => 10],
                ],
                'conversion.cta_wording' => [
                    'label' => 'CTA wording is specific and action-led',
                    'weight' => 1.2,
                    'severity' => 'medium',
                    'confidence' => 82,
                    'finding' => 'CTA wording is vague or non-committal',
                    'recommendation' => 'Replace generic labels such as "Submit" or "Learn more" with specific value-led wording such as "Book a 20-minute demo".',
                    'opportunity' => ['impact' => 7, 'effort' => 2, 'reach' => 8, 'funnel' => 8],
                ],
                'conversion.cta_visibility' => [
                    'label' => 'Primary CTA is visible near the top of the page',
                    'weight' => 1.3,
                    'severity' => 'high',
                    'confidence' => 75,
                    'finding' => 'Primary CTA is not visible in the opening viewport',
                    'recommendation' => 'Place the primary CTA in the hero area and repeat it after each major proof or benefit section.',
                    'opportunity' => ['impact' => 8, 'effort' => 3, 'reach' => 8, 'funnel' => 9],
                ],
                'conversion.form_present' => [
                    'label' => 'A lead or enquiry form is available',
                    'weight' => 1.5,
                    'severity' => 'high',
                    'confidence' => 90,
                    'finding' => 'No conversion form was found on key pages',
                    'recommendation' => 'Provide a short enquiry form on the contact page and a secondary lead-capture route from high-intent pages.',
                    'opportunity' => ['impact' => 9, 'effort' => 4, 'reach' => 8, 'funnel' => 10],
                ],
                'conversion.form_complexity' => [
                    'label' => 'Forms ask only for essential information',
                    'weight' => 1.1,
                    'severity' => 'medium',
                    'confidence' => 88,
                    'finding' => 'Forms request more information than needed',
                    'recommendation' => 'Reduce initial forms to four essential fields and collect qualifying detail after the first contact.',
                    'opportunity' => ['impact' => 7, 'effort' => 3, 'reach' => 6, 'funnel' => 9],
                ],
                'conversion.form_positioning' => [
                    'label' => 'Forms are easy to find and reach',
                    'weight' => 0.9,
                    'severity' => 'medium',
                    'confidence' => 78,
                    'finding' => 'The main form is buried or hard to reach',
                    'recommendation' => 'Link directly to the form from primary navigation and high-intent sections, and anchor CTAs to the form.',
                    'opportunity' => ['impact' => 6, 'effort' => 2, 'reach' => 7, 'funnel' => 8],
                ],
                'conversion.lead_capture' => [
                    'label' => 'Secondary lead-capture paths exist',
                    'weight' => 0.8,
                    'severity' => 'low',
                    'confidence' => 70,
                    'finding' => 'There are few ways to capture interest beyond the main CTA',
                    'recommendation' => 'Add one low-commitment capture path such as a newsletter, checklist download or callback request.',
                    'opportunity' => ['impact' => 5, 'effort' => 4, 'reach' => 7, 'funnel' => 6],
                ],
                'conversion.journey_clarity' => [
                    'label' => 'The conversion journey is discoverable',
                    'weight' => 1.2,
                    'severity' => 'high',
                    'confidence' => 85,
                    'finding' => 'Key conversion pages are not discoverable from navigation',
                    'recommendation' => 'Expose pricing, product/service and contact pages in the header or primary navigation.',
                    'opportunity' => ['impact' => 8, 'effort' => 2, 'reach' => 8, 'funnel' => 8],
                ],
            ],
        ],

        'trust' => [
            'weight' => 0.15,
            'checks' => [
                'trust.testimonials' => [
                    'label' => 'Customer testimonials are present',
                    'weight' => 1.4,
                    'severity' => 'high',
                    'confidence' => 82,
                    'finding' => 'No customer testimonials were detected',
                    'recommendation' => 'Add attributable testimonials with a person, role and company near the primary conversion points.',
                    'opportunity' => ['impact' => 7, 'effort' => 3, 'reach' => 7, 'funnel' => 8],
                ],
                'trust.reviews' => [
                    'label' => 'Ratings or reviews are visible',
                    'weight' => 1.0,
                    'severity' => 'medium',
                    'confidence' => 75,
                    'finding' => 'No review or rating evidence was detected',
                    'recommendation' => 'Display verifiable ratings or review excerpts with their source, and link to the full review profile.',
                    'opportunity' => ['impact' => 6, 'effort' => 3, 'reach' => 7, 'funnel' => 7],
                ],
                'trust.certifications' => [
                    'label' => 'Certifications or memberships are shown',
                    'weight' => 0.7,
                    'severity' => 'low',
                    'confidence' => 70,
                    'finding' => 'No certification, accreditation or membership signals were detected',
                    'recommendation' => 'Show relevant accreditations, partner badges or industry memberships with a short explanation of why each matters.',
                    'opportunity' => ['impact' => 4, 'effort' => 4, 'reach' => 5, 'funnel' => 5],
                ],
                'trust.security' => [
                    'label' => 'Security and privacy signals are clear',
                    'weight' => 1.1,
                    'severity' => 'high',
                    'confidence' => 88,
                    'finding' => 'Security or privacy reassurance is missing',
                    'recommendation' => 'Use HTTPS everywhere, link to a privacy policy and explain how submitted data is protected.',
                    'opportunity' => ['impact' => 7, 'effort' => 2, 'reach' => 8, 'funnel' => 7],
                ],
                'trust.contact_information' => [
                    'label' => 'Real contact details are published',
                    'weight' => 1.3,
                    'severity' => 'high',
                    'confidence' => 88,
                    'finding' => 'Contact information is incomplete or absent',
                    'recommendation' => 'Publish a phone number, email address and business location, and link them from the footer and contact page.',
                    'opportunity' => ['impact' => 7, 'effort' => 1, 'reach' => 9, 'funnel' => 8],
                ],
                'trust.social_proof' => [
                    'label' => 'Broader social proof is visible',
                    'weight' => 0.9,
                    'severity' => 'medium',
                    'confidence' => 68,
                    'finding' => 'Broader social proof is limited',
                    'recommendation' => 'Add client logos, case studies, customer counts or before/after outcomes with verifiable context.',
                    'opportunity' => ['impact' => 6, 'effort' => 5, 'reach' => 6, 'funnel' => 6],
                ],
                'trust.business_credibility' => [
                    'label' => 'Business identity and credibility are clear',
                    'weight' => 0.8,
                    'severity' => 'medium',
                    'confidence' => 72,
                    'finding' => 'Business identity is under-explained',
                    'recommendation' => 'Add an about page with the legal business name, team, location and a concise credibility statement.',
                    'opportunity' => ['impact' => 5, 'effort' => 4, 'reach' => 6, 'funnel' => 5],
                ],
            ],
        ],

        'technical' => [
            'weight' => 0.15,
            'checks' => [
                'technical.performance' => [
                    'label' => 'Homepage performance score',
                    'weight' => 1.4,
                    'severity' => 'high',
                    'confidence' => 90,
                    'finding' => 'Performance score is below a healthy threshold',
                    'recommendation' => 'Prioritise largest-contentful-paint and total-blocking-time work: compress images, defer non-critical JavaScript and cache static assets.',
                    'opportunity' => ['impact' => 7, 'effort' => 5, 'reach' => 9, 'funnel' => 6],
                ],
                'technical.core_web_vitals' => [
                    'label' => 'Core Web Vitals are within thresholds',
                    'weight' => 1.3,
                    'severity' => 'high',
                    'confidence' => 85,
                    'finding' => 'One or more Core Web Vitals are outside recommended thresholds',
                    'recommendation' => 'Set explicit LCP, CLS and INP budgets and fix the largest contributor on the homepage and key landing pages.',
                    'opportunity' => ['impact' => 7, 'effort' => 5, 'reach' => 9, 'funnel' => 6],
                ],
                'technical.accessibility' => [
                    'label' => 'Automated accessibility violations',
                    'weight' => 1.1,
                    'severity' => 'high',
                    'confidence' => 92,
                    'finding' => 'Accessibility violations were detected',
                    'recommendation' => 'Fix critical axe violations first: names and labels, colour contrast, landmark structure and keyboard focus visibility.',
                    'opportunity' => ['impact' => 6, 'effort' => 4, 'reach' => 9, 'funnel' => 5],
                ],
                'technical.image_optimisation' => [
                    'label' => 'Images are described and optimised',
                    'weight' => 0.9,
                    'severity' => 'medium',
                    'confidence' => 85,
                    'finding' => 'Images are missing alternative text or optimisation attributes',
                    'recommendation' => 'Add descriptive alt text, serve responsive image sizes and use modern formats such as WebP or AVIF.',
                    'opportunity' => ['impact' => 4, 'effort' => 3, 'reach' => 8, 'funnel' => 4],
                ],
                'technical.broken_links' => [
                    'label' => 'No broken internal links',
                    'weight' => 1.0,
                    'severity' => 'medium',
                    'confidence' => 80,
                    'finding' => 'Broken internal links were found',
                    'recommendation' => 'Fix or redirect broken links and add link checking to the release process.',
                    'opportunity' => ['impact' => 4, 'effort' => 2, 'reach' => 6, 'funnel' => 4],
                ],
                'technical.js_errors' => [
                    'label' => 'No JavaScript runtime errors',
                    'weight' => 0.8,
                    'severity' => 'medium',
                    'confidence' => 88,
                    'finding' => 'JavaScript errors occurred while rendering pages',
                    'recommendation' => 'Resolve uncaught JavaScript errors and add client-side error monitoring before changing conversion flows.',
                    'opportunity' => ['impact' => 5, 'effort' => 4, 'reach' => 7, 'funnel' => 4],
                ],
                'technical.mobile_performance' => [
                    'label' => 'Mobile experience is responsive and usable',
                    'weight' => 1.0,
                    'severity' => 'high',
                    'confidence' => 80,
                    'finding' => 'Mobile usability risks were detected',
                    'recommendation' => 'Test and fix the primary conversion path on a throttled mobile connection and a 360px viewport.',
                    'opportunity' => ['impact' => 7, 'effort' => 4, 'reach' => 8, 'funnel' => 7],
                ],
            ],
        ],

        'messaging' => [
            'weight' => 0.25,
            'checks' => [
                'messaging.headline_clarity' => [
                    'label' => 'Headline communicates the offer',
                    'weight' => 1.5,
                    'severity' => 'high',
                    'confidence' => 80,
                    'finding' => 'The main headline is unclear or generic',
                    'recommendation' => 'Rewrite the H1 so a first-time visitor understands who the offer is for and what outcome they get within five seconds.',
                    'opportunity' => ['impact' => 9, 'effort' => 2, 'reach' => 9, 'funnel' => 9],
                ],
                'messaging.value_proposition' => [
                    'label' => 'A distinct value proposition is present',
                    'weight' => 1.4,
                    'severity' => 'high',
                    'confidence' => 78,
                    'finding' => 'The value proposition is missing or unspecific',
                    'recommendation' => 'Add a one-sentence value proposition beneath the headline naming the outcome, audience and proof point.',
                    'opportunity' => ['impact' => 8, 'effort' => 2, 'reach' => 9, 'funnel' => 8],
                ],
                'messaging.benefit_communication' => [
                    'label' => 'Benefits are explained, not just features',
                    'weight' => 1.1,
                    'severity' => 'medium',
                    'confidence' => 75,
                    'finding' => 'Copy lists features without connecting them to outcomes',
                    'recommendation' => 'Pair each feature with the practical benefit or outcome it creates for the visitor.',
                    'opportunity' => ['impact' => 6, 'effort' => 3, 'reach' => 7, 'funnel' => 6],
                ],
                'messaging.audience_relevance' => [
                    'label' => 'The intended audience is explicit',
                    'weight' => 0.9,
                    'severity' => 'medium',
                    'confidence' => 72,
                    'finding' => 'The page does not clearly name who the offer is for',
                    'recommendation' => 'Name the target audience and the situation they are in, then speak to that situation in the hero copy.',
                    'opportunity' => ['impact' => 6, 'effort' => 2, 'reach' => 8, 'funnel' => 6],
                ],
                'messaging.differentiation' => [
                    'label' => 'Differentiation is explicit',
                    'weight' => 0.9,
                    'severity' => 'medium',
                    'confidence' => 68,
                    'finding' => 'The page does not explain why the offer is different',
                    'recommendation' => 'State one credible differentiator and support it with evidence, process detail or a comparison.',
                    'opportunity' => ['impact' => 6, 'effort' => 4, 'reach' => 7, 'funnel' => 5],
                ],
                'messaging.offer_clarity' => [
                    'label' => 'The next step and offer are explicit',
                    'weight' => 1.2,
                    'severity' => 'high',
                    'confidence' => 80,
                    'finding' => 'What happens next is not explicit',
                    'recommendation' => 'Explain the offer, price or next step, and set expectations for what happens after the visitor converts.',
                    'opportunity' => ['impact' => 7, 'effort' => 2, 'reach' => 8, 'funnel' => 8],
                ],
                'messaging.message_consistency' => [
                    'label' => 'Title, headline and meta description are aligned',
                    'weight' => 0.8,
                    'severity' => 'low',
                    'confidence' => 78,
                    'finding' => 'Page title, headline and meta description tell different stories',
                    'recommendation' => 'Align the page title, H1 and meta description around one primary promise and one audience.',
                    'opportunity' => ['impact' => 4, 'effort' => 2, 'reach' => 8, 'funnel' => 4],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Crawl defaults
    |--------------------------------------------------------------------------
    */
    'crawl' => [
        'max_pages' => (int) env('CRO_CRAWL_MAX_PAGES', 30),
        'max_depth' => (int) env('CRO_CRAWL_MAX_DEPTH', 3),
        'page_timeout_ms' => (int) env('CRO_CRAWL_PAGE_TIMEOUT_MS', 30000),
        'total_timeout_ms' => (int) env('CRO_CRAWL_TOTAL_TIMEOUT_MS', 900000),
        'nav_timeout_ms' => (int) env('CRO_CRAWL_NAV_TIMEOUT_MS', 45000),
        'delay_ms' => (int) env('CRO_CRAWL_DELAY_MS', 350),
        'max_retries' => 1,
        'viewport' => ['width' => 1440, 'height' => 1000],
        'mobile_viewport' => ['width' => 390, 'height' => 844],
        'user_agent' => 'Mozilla/5.0 (compatible; PredictiveCROBot/1.0; +https://example.com/bot)',
        'respect_robots' => true,
        'screenshot_quality' => 65,
        'lighthouse_enabled' => (bool) env('CRO_LIGHTHOUSE_ENABLED', true),
        'lighthouse_timeout_ms' => 120000,
        'broken_link_checks' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Browser worker
    |--------------------------------------------------------------------------
    |
    | The crawler is implemented as an isolated Node/Playwright worker. It can
    | either be executed as a child process on the same host ("process") or as
    | a separate HTTP worker service ("http") when the web host cannot run
    | Chromium. See docs/deployment-etios.md for the required worker contract.
    |
    */
    'worker' => [
        'mode' => env('CRO_WORKER_MODE', 'process'),
        'node_binary' => env('CRO_NODE_BINARY', 'node'),
        'path' => env('CRO_WORKER_PATH', base_path('browser-worker')),
        'url' => env('CRO_WORKER_URL', 'http://127.0.0.1:8787'),
        'token' => env('CRO_WORKER_TOKEN'),
        'poll_interval_ms' => (int) env('CRO_WORKER_POLL_MS', 750),
        'max_runtime_seconds' => (int) env('CRO_WORKER_MAX_RUNTIME', 1200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Technical thresholds
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        'lighthouse_good' => 90,
        'lighthouse_warn' => 50,
        'lcp_good_ms' => 2500,
        'lcp_warn_ms' => 4000,
        'cls_good' => 0.1,
        'cls_warn' => 0.25,
        'inp_good_ms' => 200,
        'inp_warn_ms' => 500,
        'tbt_good_ms' => 200,
        'tbt_warn_ms' => 600,
        'max_form_fields' => 6,
        'max_form_fields_warn' => 4,
        'max_primary_ctas' => 3,
        'min_nav_links' => 3,
        'max_nav_links' => 12,
        'max_headline_words' => 14,
        'min_word_count' => 250,
        'max_word_count_warn' => 2500,
        'alt_text_coverage' => 0.9,
        'axe_critical_violations' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversion friction rules
    |--------------------------------------------------------------------------
    |
    | Each penalty is configurable. The final friction score is capped at 100.
    | It is a heuristic index of friction detected in page evidence, not a
    | measured abandonment rate.
    |
    */
    'friction' => [
        'form_fields_over_6' => ['penalty' => 16, 'label' => 'Long forms'],
        'form_fields_over_4' => ['penalty' => 8, 'label' => 'Moderately long forms'],
        'multiple_primary_ctas' => ['penalty' => 10, 'label' => 'Competing primary CTAs'],
        'no_primary_cta' => ['penalty' => 25, 'label' => 'No primary CTA'],
        'missing_contact_info' => ['penalty' => 14, 'label' => 'No contact details'],
        'missing_trust_proof' => ['penalty' => 12, 'label' => 'No trust proof'],
        'no_pricing_or_offer' => ['penalty' => 8, 'label' => 'Offer or pricing unclear'],
        'no_lead_capture' => ['penalty' => 8, 'label' => 'No secondary lead capture'],
        'vague_cta_wording' => ['penalty' => 9, 'label' => 'Vague CTA wording'],
        'long_dense_copy' => ['penalty' => 5, 'label' => 'Dense copy'],
        'js_errors' => ['penalty' => 6, 'label' => 'JavaScript errors'],
        'accessibility_violations' => ['penalty' => 6, 'label' => 'Accessibility barriers'],
        'mobile_issues' => ['penalty' => 8, 'label' => 'Mobile usability issues'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Opportunity prioritisation
    |--------------------------------------------------------------------------
    | Score = (impact * confidence * reach * funnel) / max(effort, 1)
    | All inputs are 1-10. Scores are normalised for display only.
    */
    'opportunity' => [
        'scale_max' => 10,
        'min_effort' => 1,
        'max_opportunities' => 25,
        'severity_impact_floor' => [
            'info' => 1,
            'low' => 3,
            'medium' => 5,
            'high' => 7,
            'critical' => 9,
        ],
        'page_reach' => [
            'home' => 10,
            'landing' => 9,
            'pricing' => 9,
            'product' => 8,
            'service' => 8,
            'contact' => 7,
            'about' => 5,
            'blog' => 4,
            'legal' => 2,
            'other' => 4,
        ],
    ],

    'friction_max' => 100,

    'ai' => [
        'enabled' => (bool) env('CRO_AI_ENABLED', true),
        'provider' => env('CRO_AI_PROVIDER', 'openai'),
        'base_url' => env('CRO_AI_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('CRO_AI_API_KEY'),
        'model' => env('CRO_AI_MODEL', 'gpt-4o-mini'),
        'prompt_version' => 'cro-analysis-v1',
        'temperature' => 0.2,
        'max_tokens' => (int) env('CRO_AI_MAX_TOKENS', 3000),
        'timeout' => (int) env('CRO_AI_TIMEOUT', 90),
        'retries' => 2,
        'max_findings' => 40,
        'max_opportunities' => 15,
        'max_recommendations' => 12,
        'max_input_chars' => 60000,
        'max_cost_usd_per_run' => (float) env('CRO_AI_MAX_COST_USD', 0.50),
        'input_cost_per_million' => (float) env('CRO_AI_INPUT_COST_PER_MILLION', 0.15),
        'output_cost_per_million' => (float) env('CRO_AI_OUTPUT_COST_PER_MILLION', 0.60),
    ],

    'report' => [
        'max_findings' => 30,
        'top_opportunities' => 10,
        'max_recommendations' => 10,
        'brand' => [
            'name' => env('CRO_BRAND_NAME', 'Predictive CRO Intelligence'),
            'color' => '#4f46e5',
            'accent' => '#8b5cf6',
        ],
    ],
];
