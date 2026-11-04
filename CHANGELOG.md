# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Typed client of the dane.gov.pl API (JSON:API, version 1.4): datasets, resources, institutions, showcases and search.
- `Query` builder for full-text search, bracket filters with lookups, sorting, facets and includes.
- Lazy iterators and `Page` objects with totals, facets and aggregations; guard against the 10 000 record window.
- `TabularReader` streaming the rows of tabular resources with filters, ranges, sorting, sums and averages.
- Streaming download of resource files and their converted formats.
- Response cache, API version and language selection, retries of reads.
- `dane:search` and `dane:resource` Artisan commands.
