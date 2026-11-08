/**
 * Browser-side extraction. This function is serialised into Playwright's
 * page.evaluate, so every helper must live inside it.
 */
export async function extractPage(page) {
    return page.evaluate(() => {
        const clean = (value) => String(value ?? '').replace(/\s+/g, ' ').trim();
        const textOf = (element) => clean(element?.innerText ?? element?.textContent ?? element?.value ?? '');
        const classOf = (element) => String(element?.getAttribute?.('class') ?? '');
        const styleOf = (element) => {
            try {
                return window.getComputedStyle(element);
            } catch {
                return null;
            }
        };
        const isVisible = (element) => {
            if (!element || element.nodeType !== 1) {
                return false;
            }

            const style = styleOf(element);
            const rect = element.getBoundingClientRect();

            return Boolean(
                style
                && style.display !== 'none'
                && style.visibility !== 'hidden'
                && Number.parseFloat(style.opacity || '1') > 0.05
                && rect.width > 1
                && rect.height > 1
                && rect.bottom > -200
                && rect.top < (document.documentElement.scrollHeight + 200),
            );
        };
        const rectInfo = (element) => {
            const rect = element.getBoundingClientRect();

            return {
                top: Math.round(rect.top + window.scrollY),
                left: Math.round(rect.left + window.scrollX),
                width: Math.round(rect.width),
                height: Math.round(rect.height),
                above_fold: rect.top < window.innerHeight && rect.bottom > 0,
            };
        };
        const selectorFor = (element) => {
            if (!element || element.nodeType !== 1) {
                return null;
            }

            if (element.id) {
                return `#${CSS.escape(element.id)}`;
            }

            const parts = [];
            let node = element;
            let depth = 0;

            while (node && node.nodeType === 1 && node !== document.body && depth < 4) {
                let part = node.tagName.toLowerCase();
                const parent = node.parentElement;

                if (parent) {
                    const siblings = [...parent.children].filter((child) => child.tagName === node.tagName);

                    if (siblings.length > 1) {
                        part += `:nth-of-type(${siblings.indexOf(node) + 1})`;
                    }
                }

                parts.unshift(part);
                node = node.parentElement;
                depth += 1;
            }

            return parts.join(' > ');
        };
        const absolute = (href) => {
            if (!href) {
                return null;
            }

            try {
                const url = new URL(href, window.location.href);

                if (!['http:', 'https:'].includes(url.protocol)) {
                    return null;
                }

                url.hash = '';
                return url.href;
            } catch {
                return null;
            }
        };
        const meta = (name, attribute = 'name') => {
            const element = document.querySelector(`meta[${attribute}="${name}"]`);
            return element ? clean(element.getAttribute('content')) : null;
        };
        const dedupe = (items, key) => {
            const seen = new Set();

            return items.filter((item) => {
                const value = key(item);

                if (!value || seen.has(value)) {
                    return false;
                }

                seen.add(value);
                return true;
            });
        };

        const bodyText = clean(document.body?.innerText ?? '');
        const mainElement = document.querySelector('main, [role="main"]');
        const mainText = clean(mainElement?.innerText ?? '');
        const words = bodyText.toLowerCase().match(/[a-z0-9][a-z0-9'’-]*/g) ?? [];
        const sentences = bodyText.split(/[.!?]+(?:\s|$)/).filter((sentence) => clean(sentence).length > 2);
        const syllableCount = (word) => {
            let value = String(word).toLowerCase().replace(/[^a-z]/g, '');

            if (!value) {
                return 0;
            }

            if (value.length <= 3) {
                return 1;
            }

            value = value.replace(/(?:[^laeiouy]es|ed|[^laeiouy]e)$/, '').replace(/^y/, '');
            const groups = value.match(/[aeiouy]{1,2}/g);

            return Math.max(1, groups ? groups.length : 1);
        };
        const syllableTotal = words.reduce((total, word) => total + syllableCount(word), 0);
        const readingEase = words.length > 0 && sentences.length > 0
            ? Math.max(0, Math.min(100, 206.835 - (1.015 * (words.length / sentences.length)) - (84.6 * (syllableTotal / words.length))))
            : null;
        const longWordRatio = words.length > 0
            ? words.filter((word) => syllableCount(word) >= 4).length / words.length
            : 0;

        const headings = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')]
            .filter(isVisible)
            .slice(0, 150)
            .map((heading, index) => ({
                level: Number(heading.tagName.substring(1)),
                text: textOf(heading).slice(0, 300),
                selector: selectorFor(heading),
                position: index,
            }))
            .filter((heading) => heading.text !== '');

        const h1s = [...document.querySelectorAll('h1')].filter(isVisible).map((heading) => textOf(heading)).filter(Boolean);

        const navElements = [...document.querySelectorAll('header nav, nav, [role="navigation"]')].filter(isVisible);
        const navLinks = dedupe(
            navElements.flatMap((nav) => [...nav.querySelectorAll('a[href]')]).slice(0, 80).map((anchor) => ({
                text: (textOf(anchor) || clean(anchor.getAttribute('aria-label'))).slice(0, 120),
                href: absolute(anchor.getAttribute('href')),
                title: clean(anchor.getAttribute('title')) || null,
            })),
            (link) => `${link.text}|${link.href}`,
        ).slice(0, 30);

        const links = [...document.querySelectorAll('a[href]')]
            .slice(0, 600)
            .map((anchor) => ({
                text: (textOf(anchor) || clean(anchor.getAttribute('aria-label'))).slice(0, 200),
                href: absolute(anchor.getAttribute('href')),
                rel: clean(anchor.getAttribute('rel')) || null,
                target: clean(anchor.getAttribute('target')) || null,
                title: clean(anchor.getAttribute('title')) || null,
                visible: isVisible(anchor),
            }))
            .filter((link) => link.href !== null)
            .slice(0, 400);

        const actionPattern = /\b(get started|start now|start free|book|demo|contact|call|buy|purchase|order|sign up|signup|subscribe|request|quote|enquire|inquire|schedule|apply|download|try|shop|checkout|add to cart|talk to|speak to|let'?s talk|learn more|discover|explore)\b/i;
        const primaryClassPattern = /\b(primary|cta|hero|btn-primary|button-primary|main-cta)\b/i;

        const rawButtons = [
            ...document.querySelectorAll('button, input[type="submit"], input[type="button"], a[role="button"], a[class*="btn" i], a[class*="button" i], a[class*="cta" i]'),
        ];

        const buttons = dedupe(
            rawButtons.slice(0, 150).map((element) => {
                const text = textOf(element) || clean(element.value) || clean(element.getAttribute('aria-label'));
                const style = styleOf(element);
                const classes = classOf(element);
                const background = style?.backgroundColor ?? '';
                const backgroundVisible = background !== '' && background !== 'transparent' && !/rgba\(0,\s*0,\s*0,\s*0\)/.test(background);
                const isSubmit = element.tagName === 'BUTTON' || element.type === 'submit';

                return {
                    text: text.slice(0, 160),
                    tag: element.tagName.toLowerCase(),
                    type: element.getAttribute('type') || null,
                    href: absolute(element.getAttribute('href')),
                    aria_label: clean(element.getAttribute('aria-label')) || null,
                    classes: classes.slice(0, 240),
                    selector: selectorFor(element),
                    visible: isVisible(element),
                    above_fold: isVisible(element) && rectInfo(element).above_fold,
                    rect: rectInfo(element),
                    is_submit: isSubmit,
                    is_primary: primaryClassPattern.test(classes) || (backgroundVisible && actionPattern.test(text)),
                    background,
                    color: style?.color ?? null,
                };
            }).filter((button) => button.text !== ''),
            (button) => `${button.text}|${button.href}|${button.tag}`,
        ).slice(0, 80);

        const ctas = buttons
            .filter((button) => button.visible && (actionPattern.test(button.text) || button.is_primary || button.is_submit))
            .slice(0, 40);

        const forms = [...document.querySelectorAll('form, [role="form"]')].slice(0, 10).map((form) => {
            const fields = [...form.querySelectorAll('input, select, textarea')]
                .filter((field) => !['hidden', 'submit', 'button', 'reset', 'image'].includes((field.getAttribute('type') || '').toLowerCase()))
                .slice(0, 40)
                .map((field) => {
                    const id = field.getAttribute('id');
                    const explicitLabel = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
                    const wrappingLabel = field.closest('label');
                    const label = clean(
                        textOf(explicitLabel)
                        || textOf(wrappingLabel)
                        || field.getAttribute('aria-label')
                        || field.getAttribute('placeholder')
                        || field.getAttribute('name'),
                    );

                    return {
                        type: (field.getAttribute('type') || field.tagName).toLowerCase(),
                        name: field.getAttribute('name'),
                        id,
                        label: label.slice(0, 160),
                        placeholder: clean(field.getAttribute('placeholder')) || null,
                        required: field.required || field.getAttribute('aria-required') === 'true',
                        autocomplete: field.getAttribute('autocomplete'),
                    };
                });
            const submit = form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
            const rect = rectInfo(form);

            return {
                action: absolute(form.getAttribute('action')) ?? window.location.href,
                method: (form.getAttribute('method') || 'get').toLowerCase(),
                fields,
                field_count: fields.length,
                has_email: fields.some((field) => field.type === 'email' || /e-?mail/i.test(field.name ?? '') || /e-?mail/i.test(field.label ?? '')),
                has_phone: fields.some((field) => field.type === 'tel' || /phone|mobile|telephone/i.test(field.name ?? '') || /phone|mobile/i.test(field.label ?? '')),
                has_message: fields.some((field) => field.type === 'textarea' || /message|comment|enquiry|inquiry/i.test(field.name ?? '')),
                submit_text: submit ? textOf(submit).slice(0, 120) : null,
                above_fold: rect.above_fold,
                rect,
                selector: selectorFor(form),
                visible: isVisible(form),
            };
        });

        const images = [...document.querySelectorAll('img')].slice(0, 200).map((image) => ({
            src: absolute(image.getAttribute('src') ?? image.getAttribute('data-src'))?.slice(0, 2000) ?? null,
            alt: clean(image.getAttribute('alt')),
            has_alt: image.hasAttribute('alt'),
            width: image.getAttribute('width') ? Number(image.getAttribute('width')) : null,
            height: image.getAttribute('height') ? Number(image.getAttribute('height')) : null,
            natural_width: image.naturalWidth || null,
            natural_height: image.naturalHeight || null,
            loading: image.getAttribute('loading'),
            above_fold: isVisible(image) && rectInfo(image).above_fold,
            visible: isVisible(image),
        }));

        const testimonialCandidates = [
            ...document.querySelectorAll('blockquote, figure, [class*="testimonial" i], [class*="review" i], [class*="quote" i], [data-testimonial]'),
        ];
        const testimonials = dedupe(
            testimonialCandidates.slice(0, 80).map((element) => {
                const text = textOf(element).slice(0, 900);
                const authorElement = element.querySelector('cite, [class*="author" i], [class*="name" i], [itemprop="author"], footer');
                const ratingText = clean(element.querySelector('[class*="star" i], [class*="rating" i]')?.innerText ?? '');

                return {
                    text,
                    author: authorElement ? textOf(authorElement).slice(0, 160) : null,
                    rating_text: ratingText.slice(0, 80) || null,
                    selector: selectorFor(element),
                };
            }).filter((item) => item.text.length >= 40 && item.text.length <= 900),
            (item) => item.text,
        ).slice(0, 12);

        const reviewElements = [...document.querySelectorAll('[class*="review" i], [itemprop="review"]')]
            .filter(isVisible)
            .slice(0, 40);

        const structuredData = [];
        const structuredTypes = new Set();
        let aggregateRating = null;
        let structuredName = null;
        let structuredPhone = null;
        let structuredAddress = null;

        const walkStructured = (value) => {
            if (Array.isArray(value)) {
                value.forEach(walkStructured);
                return;
            }

            if (!value || typeof value !== 'object') {
                return;
            }

            const types = Array.isArray(value['@type']) ? value['@type'] : [value['@type']].filter(Boolean);
            types.forEach((type) => structuredTypes.add(String(type)));

            if (value.aggregateRating && typeof value.aggregateRating === 'object') {
                aggregateRating = {
                    rating_value: Number(value.aggregateRating.ratingValue) || null,
                    review_count: Number(value.aggregateRating.reviewCount ?? value.aggregateRating.ratingCount) || null,
                    best_rating: Number(value.aggregateRating.bestRating) || null,
                };
            }

            if (value.name && typeof value.name === 'string' && structuredName === null) {
                structuredName = value.name;
            }

            if (value.telephone && structuredPhone === null) {
                structuredPhone = String(value.telephone);
            }

            if (value.address && structuredAddress === null) {
                structuredAddress = typeof value.address === 'string' ? value.address : clean(value.address.streetAddress ?? '');
            }

            for (const key of ['@graph', 'mainEntity', 'itemListElement', 'hasOfferCatalog', 'review']) {
                if (value[key]) {
                    walkStructured(value[key]);
                }
            }
        };

        [...document.querySelectorAll('script[type="application/ld+json"]')].slice(0, 20).forEach((script) => {
            try {
                const parsed = JSON.parse(script.textContent);
                structuredData.push(parsed);
                walkStructured(parsed);
            } catch {
                // Invalid JSON-LD is itself a technical signal, but not fatal.
            }
        });

        const emailPattern = /[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/gi;
        const phonePattern = /(?:\+?\d{1,3}[\s.-]?)?(?:\(?\d{2,4}\)?[\s.-]?){2,4}\d{2,4}/g;
        const bodyEmails = [...new Set((bodyText.match(emailPattern) ?? []))].slice(0, 5);
        const emails = [...new Set([
            ...bodyEmails,
            ...[...document.querySelectorAll('a[href^="mailto:"]')]
                .map((anchor) => clean(anchor.getAttribute('href')).replace(/^mailto:/i, '').split('?')[0])
                .filter(Boolean),
        ])].slice(0, 5);
        const phones = [...new Set([
            ...[...document.querySelectorAll('a[href^="tel:"]')].map((anchor) => clean(anchor.getAttribute('href')).replace(/^tel:/i, '')),
            ...(bodyText.match(phonePattern) ?? []).filter((candidate) => {
                const digits = candidate.replace(/\D/g, '').length;
                return digits >= 8 && digits <= 15;
            }),
        ])].slice(0, 5);
        const addresses = [
            ...[...document.querySelectorAll('address')].map((element) => textOf(element)).filter((text) => text.length > 8),
            ...(structuredAddress ? [structuredAddress] : []),
        ].slice(0, 3);

        const socialHosts = ['facebook.com', 'linkedin.com', 'twitter.com', 'x.com', 'instagram.com', 'youtube.com', 'tiktok.com', 'pinterest.com', 'github.com'];
        const socialLinks = dedupe(
            [...document.querySelectorAll('a[href]')]
                .map((anchor) => absolute(anchor.getAttribute('href')))
                .filter((href) => href && socialHosts.some((host) => new URL(href).hostname.replace(/^www\./, '').endsWith(host)))
                .map((href) => ({ href, network: new URL(href).hostname.replace(/^www\./, '') })),
            (link) => link.href,
        ).slice(0, 12);

        const patterns = [
            { key: 'iso', label: 'ISO certification', regex: /\bISO\s?(9001|27001|14001|45001)\b/i },
            { key: 'soc2', label: 'SOC 2', regex: /\bSOC\s?2\b/i },
            { key: 'gdpr', label: 'GDPR', regex: /\bGDPR\b/i },
            { key: 'hipaa', label: 'HIPAA', regex: /\bHIPAA\b/i },
            { key: 'pci', label: 'PCI DSS', regex: /\bPCI[\s-]?DSS\b/i },
            { key: 'cyber_essentials', label: 'Cyber Essentials', regex: /Cyber\s?Essentials/i },
            { key: 'bcorp', label: 'B Corp', regex: /\bB[\s-]?Corp\b/i },
            { key: 'certified', label: 'Certified / accredited', regex: /\b(certified|accredited|licensed|registered)\b/i },
            { key: 'award', label: 'Award-winning', regex: /\b(award[\s-]?winning|winner|finalist)\b/i },
            { key: 'guarantee', label: 'Guarantee / warranty', regex: /\b(money[\s-]?back|guarantee|warranty|risk[\s-]?free)\b/i },
            { key: 'secure', label: 'Secure / encrypted', regex: /\b(secure|encrypted|ssl|protected)\b/i },
            { key: 'trusted', label: 'Trusted by', regex: /\b(trusted by|used by|chosen by)\b/i },
            { key: 'featured', label: 'Featured in', regex: /\b(featured in|as seen in|press)\b/i },
            { key: 'case_study', label: 'Case study', regex: /\b(case study|case studies|success stor(?:y|ies))\b/i },
            { key: 'customers', label: 'Customer count', regex: /\b\d[\d,.]*\+?\s?(customers|clients|businesses|companies|users)\b/i },
            { key: 'experience', label: 'Years of experience', regex: /\b(since|established|founded)\s?(19|20)\d{2}\b|\b\d+\+?\s?years? (?:of )?experience\b/i },
            { key: 'rating', label: 'Rating', regex: /\b(\d(?:\.\d)?\s?(?:\/\s?5|stars?|out of 5))\b/i },
        ];

        const trustSignals = patterns
            .map((pattern) => {
                const match = bodyText.match(pattern.regex);

                if (!match) {
                    return null;
                }

                const index = Math.max(0, bodyText.toLowerCase().indexOf(match[0].toLowerCase()));
                const context = bodyText.slice(Math.max(0, index - 70), Math.min(bodyText.length, index + match[0].length + 70));

                return { key: pattern.key, label: pattern.label, match: match[0], context: clean(context) };
            })
            .filter(Boolean)
            .slice(0, 20);

        const privacyLinks = [...document.querySelectorAll('a[href]')].filter((anchor) => {
            const haystack = `${textOf(anchor)} ${anchor.getAttribute('href')}`.toLowerCase();
            return /privacy|data protection|terms|cookie|legal|gdpr/.test(haystack);
        }).slice(0, 6).map((anchor) => ({ text: textOf(anchor).slice(0, 120), href: absolute(anchor.getAttribute('href')) }));

        const consentCandidates = [...document.querySelectorAll('[id*="cookie" i], [class*="cookie" i], [id*="consent" i], [class*="consent" i], [id*="gdpr" i], [class*="gdpr" i], [aria-label*="cookie" i]')];
        const consentBanner = consentCandidates.some((element) => isVisible(element) && textOf(element).length > 25);

        const horizontalOverflow = document.documentElement.scrollWidth > window.innerWidth + 3;
        const interactiveElements = [...document.querySelectorAll('a[href], button, input, select, textarea')].filter(isVisible);
        const smallTapTargets = interactiveElements.filter((element) => {
            const rect = element.getBoundingClientRect();
            return rect.width > 0 && rect.height > 0 && (rect.width < 32 || rect.height < 32);
        }).length;

        const resources = performance.getEntriesByType('resource');
        const navigation = performance.getEntriesByType('navigation')[0];
        const imageCount = images.filter((image) => image.visible).length;
        const missingAlt = images.filter((image) => image.visible && !image.has_alt).length;
        const lazyImages = images.filter((image) => image.loading === 'lazy').length;

        return {
            url: window.location.href,
            path: window.location.pathname,
            title: clean(document.title).slice(0, 500),
            meta_description: meta('description') ?? meta('og:description', 'property'),
            canonical_url: absolute(document.querySelector('link[rel="canonical"]')?.getAttribute('href')),
            robots_meta: meta('robots'),
            lang: document.documentElement.getAttribute('lang'),
            viewport_meta: meta('viewport'),
            theme_color: meta('theme-color'),
            favicon: absolute(document.querySelector('link[rel*="icon"]')?.getAttribute('href')),
            og: {
                title: meta('og:title', 'property'),
                description: meta('og:description', 'property'),
                image: absolute(meta('og:image', 'property')),
                site_name: meta('og:site_name', 'property'),
                type: meta('og:type', 'property'),
            },
            twitter: {
                card: meta('twitter:card'),
                title: meta('twitter:title'),
                description: meta('twitter:description'),
            },
            headings,
            h1_text: h1s[0] ?? null,
            h1_count: h1s.length,
            nav_links: navLinks,
            nav_count: navLinks.length,
            links,
            buttons,
            ctas,
            above_fold_ctas: ctas.filter((cta) => cta.above_fold).length,
            primary_ctas: ctas.filter((cta) => cta.is_primary).length,
            forms,
            form_count: forms.length,
            images,
            image_count: imageCount,
            images_missing_alt: missingAlt,
            lazy_images: lazyImages,
            testimonials,
            review_count_on_page: reviewElements.length,
            aggregate_rating: aggregateRating,
            structured_types: [...structuredTypes].slice(0, 30),
            structured_name: structuredName,
            contact: {
                emails,
                phones,
                addresses,
                has_tel_link: document.querySelectorAll('a[href^="tel:"]').length > 0,
                has_mailto_link: document.querySelectorAll('a[href^="mailto:"]').length > 0,
            },
            social_links: socialLinks,
            trust_signals: trustSignals,
            privacy_links: privacyLinks,
            consent_banner: consentBanner,
            body_text: bodyText.slice(0, 12000),
            main_text: mainText.slice(0, 12000),
            intro_text: mainText.slice(0, 900),
            word_count: words.length,
            sentence_count: sentences.length,
            avg_sentence_words: sentences.length > 0 ? Number((words.length / sentences.length).toFixed(1)) : null,
            reading_ease: readingEase !== null ? Number(readingEase.toFixed(1)) : null,
            long_word_ratio: Number(longWordRatio.toFixed(3)),
            paragraph_count: [...document.querySelectorAll('p')].filter((paragraph) => textOf(paragraph).length > 40).length,
            section_count: document.querySelectorAll('section').length,
            has_header: Boolean(document.querySelector('header')),
            has_main: Boolean(mainElement),
            has_footer: Boolean(document.querySelector('footer')),
            has_nav: Boolean(document.querySelector('nav, [role="navigation"]')),
            has_sidebar: Boolean(document.querySelector('aside')),
            h1_count_visible: h1s.length,
            h1_texts: h1s.slice(0, 5),
            horizontal_overflow: horizontalOverflow,
            small_tap_targets: smallTapTargets,
            password_fields: document.querySelectorAll('input[type="password"]').length,
            resource_count: resources.length,
            transfer_size_bytes: resources.reduce((total, entry) => total + (entry.transferSize || 0), 0),
            dom_content_loaded_ms: navigation ? Math.round(navigation.domContentLoadedEventEnd) : null,
            load_ms: navigation ? Math.round(navigation.loadEventEnd) : null,
            html_size_bytes: document.documentElement.outerHTML.length,
            page_height: document.documentElement.scrollHeight,
            final_url: window.location.href,
        };
    });
}

export async function extractMobileSignals(page) {
    return page.evaluate(() => {
        const clean = (value) => String(value ?? '').replace(/\s+/g, ' ').trim();
        const isVisible = (element) => {
            const rect = element.getBoundingClientRect();
            const style = window.getComputedStyle(element);
            return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 1 && rect.height > 1;
        };
        const actionPattern = /\b(get started|start now|book|demo|contact|call|buy|purchase|order|sign up|subscribe|request|quote|enquire|schedule|apply|download|try|shop|checkout|learn more)\b/i;
        const interactive = [...document.querySelectorAll('a[href], button, input, select, textarea')].filter(isVisible);
        const ctas = [...document.querySelectorAll('button, a[class*="btn" i], a[class*="button" i], a[class*="cta" i], a[role="button"]')]
            .filter((element) => isVisible(element) && actionPattern.test(clean(element.innerText) || clean(element.value) || clean(element.getAttribute('aria-label'))));
        const styles = window.getComputedStyle(document.body);

        return {
            horizontal_overflow: document.documentElement.scrollWidth > window.innerWidth + 3,
            scroll_width: document.documentElement.scrollWidth,
            inner_width: window.innerWidth,
            viewport_meta: document.querySelector('meta[name="viewport"]')?.getAttribute('content') ?? null,
            body_font_size: styles.fontSize,
            interactive_count: interactive.length,
            small_tap_targets: interactive.filter((element) => {
                const rect = element.getBoundingClientRect();
                return rect.width > 0 && rect.height > 0 && (rect.width < 40 || rect.height < 40);
            }).length,
            cta_count: ctas.length,
            above_fold_ctas: ctas.filter((element) => {
                const rect = element.getBoundingClientRect();
                return rect.top < window.innerHeight && rect.bottom > 0;
            }).length,
            page_height: document.documentElement.scrollHeight,
        };
    });
}
