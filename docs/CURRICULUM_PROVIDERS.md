# Curriculum providers

Every provider produces the provider-neutral normalized curriculum consumed by preview and import. Metadata includes provider/source references, curriculum identity, parser version, retrieval information and a canonical checksum. Parents and criteria receive stable source keys; mutable names are not database identity.

The JSON provider accepts current and legacy code-prefixed input, validates structure, size, duplicate codes and non-negative weights, and normalizes both forms identically.

The BOE provider uses the documented AEBOE consolidated service, validates all remote destinations against SSRF policy and parses controlled FP, ESO and Bach fixtures. Parent semantic text is extracted independently from criteria, and structural boundaries prevent later normative sections from leaking into criteria. Invalid semantic structures fail before preview and cannot be confirmed as `NEW`. Automated tests never require Internet access. Provider failures use explicit states such as `SOURCE_UNAVAILABLE`; providers must not fabricate provenance or silently fall back to scraping.

Provider metadata supports future hierarchical selectors without changing Outcome structure. FP persists qualification, module code and module name; ESO persists its course-band-qualified subject label. UI grouping never rewrites an otherwise unambiguous historical `curriculumkey`.

Adding another provider requires deterministic source identity, machine-readable official input, fixture coverage, normalized-output tests and explicit unavailable/error behaviour. Provider-specific details must not leak into `import_service`.
