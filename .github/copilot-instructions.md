# CORE — GitHub Copilot Instructions

## Project Overview

CORE is a browser-based science-fiction sandbox game inspired by EVE Online, Astrox Imperium, Starfinder 1st Edition, and Traveller.

The initial development objective is to establish a modular, flexible server-side foundation before implementing extensive gameplay features.

## Technology Stack

* PHP for server-side application logic.
* HTML and CSS for the web interface.
* JavaScript for client-side behavior and future interactive graphics.
* JSON and flat-file storage for initial persistence.
* RESTful HTTP endpoints returning JSON for future external integrations.

Prefer free and open-source technologies.

## Architecture

### Object-Oriented Design and MVC

* Use object-oriented PHP and a Model-View-Controller architectural pattern.
* Keep domain logic separate from HTTP handling, presentation, and persistence.
* Organize game functionality into independent, extensible modules.
* Avoid unnecessary dependencies between modules.
* Use clearly defined interfaces and contracts when modules exchange data.

### Modular Game Systems

The planned systems include:

* Accounts and authentication.
* Characters.
* Universes and procedural generation.
* Mining and resource collection.
* Crafting and industry.
* Businesses and commerce.
* External API integrations.

Systems should be independently maintainable while remaining capable of interacting through documented interfaces.

For example, mining should be able to produce resource records that an inventory, crafting, or business system can validate and consume.

### Validation and Error Handling

* Validate data at module and persistence boundaries.
* Do not assume another module has provided correctly formatted data.
* Use explicit result types or documented response structures where appropriate.
* Catch exceptions at meaningful boundaries and log actionable diagnostic information.
* Do not silently discard failures.
* Do not use exceptions for ordinary gameplay outcomes.
* Avoid broad exception handling that hides programming errors.

## Procedural Universe Generation

* Universe content will be generated deterministically from seed values.
* The same seed and generator version should produce the same generated baseline.
* Use stable unique identifiers for accounts, characters, universes, and generated entities.
* Keep deterministic generated data distinct from persistent changes made during gameplay.
* Record generator and data-format versions so saved universes can be interpreted consistently.

## Shared Universes

Players may share a universe by using a common secret seed or phrase to access the same universe dataset.

* Shared participants must resolve to the same universe identity and persistent universe state.
* Changes to shared resources, businesses, markets, and other shared entities must be stored against the appropriate universe.
* Private account information must remain separate from shared universe data.
* Knowing a universe seed must not automatically grant unrestricted access to every operation.
* Access control and universe membership must be handled explicitly.

## Accounts and Security

Accounts are expected to use a username, securely hashed password, and optional email address.

Account creation, authentication, authorization, and character ownership should have clear boundaries.

Never store plaintext passwords, hard-code credentials, or expose sensitive account data in logs or API responses.

## Persistence

Initially use JSON and flat-file storage, with a clear abstraction between domain logic and storage implementation.

* Validate data before writing it.
* Handle missing, malformed, or incompatible data explicitly.
* Use safe write procedures to reduce the risk of corrupted save files.
* Consider concurrent access when multiple players share a universe.
* Keep persistence logic out of controllers and unrelated game systems.

## RESTful API

A future RESTful API will expose selected game data as JSON for analysis tools, activity feeds, dashboards, and external applications.

* Keep API endpoints separate from domain logic.
* Reuse application services rather than duplicating game rules in endpoints.
* Design for authentication, authorization, input validation, and versioning.
* Do not expose private account information or unauthorized universe data.
* Treat write-capable external API operations as a separate design decision.

## Development Practices

* Build and test the foundation incrementally.
* Prefer readable, maintainable code over clever abstractions.
* Keep modules small and responsibilities explicit.
* Use consistent naming conventions and namespaces.
* Add tests for important domain rules, persistence behavior, and deterministic generation.
* Keep simulation logic independent of the current web interface and future rendering engine.
* Avoid implementing speculative gameplay features before the underlying architecture supports them.
* Document significant architectural decisions and unresolved design questions.

## Working Agreement

Before making substantial changes:

1. Inspect the existing project and its conventions.
2. Explain the intended changes and identify affected files.
3. Preserve working behavior unless a change is explicitly required.
4. Avoid replacing whole files when a focused modification is sufficient.
5. Identify assumptions and unresolved design decisions.
6. Provide verification steps for the changes made.

Do not assume a proposed architecture or directory structure has already been implemented. Inspect the repository before relying on existing files or classes.
