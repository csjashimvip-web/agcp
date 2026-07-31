# Changelog

## 1.0.0 — Core platform completion

### Added

- Phase 13 production deployment and DevOps assets
- standalone production Docker Compose topology
- CyberPanel reverse-proxy deployment guidance
- production Nginx and MySQL configuration
- CI and release artifact workflows
- Phase 14 security audit, regression, UAT and load smoke scripts
- Phase 15 launch, release, operations, recovery and handover documentation

### Safety

- no automatic database reset
- no automatic Docker volume deletion
- no production secret files included
- deployment creates a verified encrypted backup when Phase 12 tooling is available
