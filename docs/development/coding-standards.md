# Coding Standards & Quality Guidelines

## 1. PHP & Laravel Standards
- **PHP Version**: 8.2 or newer with strict type declarations (`declare(strict_types=1);`) in all services and domain classes.
- **Code Style**: PSR-12 standard, formatted automatically via **Laravel Pint**.
- **Static Analysis**: PHPStan / Larastan at Level 6 or higher.

## 2. Formatting Commands
```bash
# Check code formatting with Laravel Pint
./vendor/bin/pint --test

# Automatically fix code formatting
./vendor/bin/pint
```

## 3. Architecture & Design Rules
1. **No Fat Controllers**: Controllers must not contain business pricing formulas, insurer cURL requests, or direct state mutation queries. Controllers only validate input, call an Application Service, and return an API Resource.
2. **Decimal Precision**: All currency math must use PHP `bcmath` extension (`bcmul`, `bcdiv`, `bcadd`, `bcround`) or integer-cents representations. Never use standard floating-point `*` or `/` operators for financial calculations.
3. **Explicit Transactions**: Any multi-table update (such as creating a policy request and initial state history, or updating status and attempt records) must be wrapped in `DB::transaction(function() { ... })`.
4. **Meaningful Exceptions**: Domain failures must throw typed domain exceptions (e.g., `InvalidStateTransitionException`, `DuplicatePolicyRequestException`, `InsurerTimeoutException`) which are mapped to HTTP responses in `bootstrap/app.php` or dedicated exception handlers.
