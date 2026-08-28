# BOE provider

The implemented live path uses AEBOE's official consolidated API for search, metadata and document index/text retrieval. Live checks cover `BOE-A-2022-4975` (ESO, including Tecnología y Digitalización) and `BOE-A-2014-5591` (FP). FP/ESO/Bach parser behaviour is automated with local fixtures so normal QA does not depend on AEBOE availability.

Selection retains the hierarchy exposed by the source. FP title headings are carried forward to their current consolidated module blocks, persisted as `qualification`, and used to filter module choices. Existing module-code identity remains unchanged while it is unique; only a demonstrated same-code/cross-title collision gains a deterministic title suffix. ESO uses explicit course headings and unambiguous course-band prose/tables to group subjects. It does not guess when the source provides no unique band.

ESO/Bach parsing maps criterion groups such as `1.x` to the independently extracted semantic text in the earlier **Competencias específicas** section. A criterion-only group without a recoverable competency is rejected. FP parsing removes the internal **Criterios de evaluación** heading from RA text and stops the last criterion at centralized section boundaries such as duration, basic contents and pedagogical guidance.

The client permits only validated official HTTPS destinations and rejects private, loopback or otherwise unsafe resolutions. Browser and PHPUnit tests must use fixtures or a controlled fake provider.

The consolidated API does not expose every historical rule. AEBOE publishes original machine-readable ELI `/dof/spa/xml` resources, but no documented deterministic generic resolution from a bare `BOE-A-*` identifier to that original resource was found. Consequently `BOE-A-2009-18355` returns `SOURCE_UNAVAILABLE` through the implemented path. The plugin does not scrape HTML, guess publication dates or invent endpoints.

Some FP rules work correctly through the consolidated API, including `BOE-A-2014-5591`; some historical rules do not. A future fallback is acceptable only when an official deterministic mapping is documented and fixture/live validation can prove it.
