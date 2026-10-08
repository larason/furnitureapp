---
name: dart-fix-runtime-errors
description: Uses get_runtime_errors and lsp to fetch an active stack trace, locate the failing line, apply a fix, and verify resolution via hot_reload.
metadata:
  model: models/gemini-3.1-pro-preview
  last_modified: Fri, 24 Apr 2026 15:13:22 GMT
---
# Resolving Dart Static Analysis Errors

## Contents
- [Core Concepts & Guidelines](#core-concepts--guidelines)
  - [Type System & Soundness](#type-system--soundness)
  - [Null Safety](#null-safety)
  - [Error Handling](#error-handling)
- [Workflows](#workflows)
  - [Workflow: Runtime Error Resolution](#workflow-runtime-error-resolution)
  - [Workflow: Static Analysis Resolution](#workflow-static-analysis-resolution)
- [Examples](#examples)

## Core Concepts & Guidelines

### Type System & Soundness
Enforce Dart's sound type system to prevent runtime invalid states.

*   **Method Overrides:** Maintain sound return types (covariant) and parameter types (contravariant). Never tighten a parameter type in a subclass unless explicitly marked with the `covariant` keyword.
*   **Generics & Collections:** Add explicit type annotations to generic classes (e.g., `List<T>`, `Map<K, V>`). Never assign a `List<dynamic>` to a typed list (e.g., `List<Cat>`).
*   **Downcasting:** Avoid implicit downcasts from `dynamic`. Use explicit casts (e.g., `as List<Cat>`) when necessary, but ensure the underlying runtime type matches to prevent `TypeError` exceptions.
*   **Strict Casts:** Enable `strict-casts: true` in `analysis_options.yaml` under `analyzer: language:` to force explicit casting and catch implicit downcast errors at compile time.

### Null Safety
Eliminate static errors related to null safety by correctly managing variable initialization and nullability.

*   **Modifiers:** Apply `?` for nullable types, `!` for null assertions, and `required` for named parameters that cannot be null.
*   **Late Initialization:** Use the `late` keyword for non-nullable variables guaranteed to be initialized before use. Apply this specifically to top-level or instance variables where Dart's control flow analysis cannot definitively prove initialization.
*   **Wildcards:** Use the `_` wildcard variable (Dart 3.7+) for non-binding local variables or parameters to avoid unused variable warnings.

### Error Handling
Distinguish between recoverable exceptions and unrecoverable errors.

*   **Catching:** Catch `Exception` subtypes for recoverable failures.
*   **Errors:** Never explicitly catch `Error` or its subtypes (e.g., `TypeError`, `ArgumentError`). Errors indicate programming bugs that must be fixed, not caught. Enforce this by enabling the `avoid_catching_errors` linter rule.
*   **Rethrowing:** Use `rethrow` inside a `catch` block to propagate an exception while preserving its original stack trace.

## Workflows

### Workflow: Runtime Error Resolution

Use this sequential workflow when a running Dart or Flutter application reports
an exception or unexpected behavior. Copy the checklist to track progress.

**Task Progress:**
- [ ] 1. Retrieve the active runtime failure.
- [ ] 2. Locate the failing code.
- [ ] 3. Apply the smallest root-cause fix.
- [ ] 4. Verify the fix with hot reload.

**1. Retrieve the active runtime failure**

Use `get_runtime_errors` to collect the current exception and complete stack
trace. Do not infer the cause from a screenshot or a partial log message.

**2. Locate the failing code**

Use `lsp` with the file and line information from the stack trace to inspect
the surrounding code, references, and type information. Trace the first
application frame rather than changing the framework or symptom site first.

**3. Apply the smallest root-cause fix**

Preserve existing behavior outside the failing path. Add or update a focused
regression test when the failure can be reproduced without the running app.

**4. Verify the fix**

Use `hot_reload` and repeat the failing interaction. Confirm that the runtime
error is gone and that no new analyzer or test failures were introduced.

If hot reload cannot apply the change, perform a full restart and record that
limitation in the verification result.

### Workflow: Static Analysis Resolution

Use this sequential workflow to identify, fix, and verify static analysis errors in a Dart project. Copy the checklist to track your progress.

**Task Progress:**
- [ ] 1. Run static analyzer.
- [ ] 2. Apply automated fixes.
- [ ] 3. Resolve remaining errors manually.
- [ ] 4. Verify fixes (Feedback Loop).

**1. Run static analyzer**
Execute the Dart analyzer to identify all static errors in the target directory or file.
```bash
dart analyze . --fatal-infos
```

**2. Apply automated fixes**
Use the `dart fix` tool to automatically resolve standard linting and analysis issues.
```bash
# Preview changes
dart fix --dry-run
# Apply changes
dart fix --apply
```

**3. Resolve remaining errors manually**
Review the remaining analyzer output and apply conditional logic based on the error type:

*   **If the error is a Null Safety issue (e.g., "Property cannot be accessed on a nullable receiver"):**
    *   Verify if the variable can logically be null.
    *   If yes, use optional chaining (`?.`) or provide a fallback (`??`).
    *   If no, and initialization is guaranteed elsewhere, mark the declaration with `late`.
*   **If the error is a Type Mismatch (e.g., "The argument type 'List<dynamic>' can't be assigned..."):**
    *   Trace the variable's initialization.
    *   Add explicit generic type annotations to the instantiation (e.g., `<int>[]` instead of `[]`).
*   **If the error is an Invalid Override (e.g., "The parameter type doesn't match the overridden method"):**
    *   Widen the parameter type to match the superclass, OR
    *   Add the `covariant` keyword to the parameter if tightening the type is intentionally required by the domain logic.

**4. Verify fixes (Feedback Loop)**
Run the validator. Review errors. Fix.
```bash
dart analyze .
dart test
```
*   **If `dart analyze` reports errors:** Return to Step 3.
*   **If `dart test` fails with a `TypeError`:** You have introduced an invalid explicit cast (`as T`) or accessed an uninitialized `late` variable. Locate the runtime failure and correct the type hierarchy or initialization order.

## Examples

### Example: Fixing Dynamic List Assignments
**Input (Fails Static Analysis):**
```dart
void printInts(List<int> a) => print(a);

void main() {
  final list = []; // Inferred as List<dynamic>
  list.add(1);
  list.add(2);
  printInts(list); // Error: List<dynamic> can't be assigned to List<int>
}
```

**Output (Passes Static Analysis):**
```dart
void printInts(List<int> a) => print(a);

void main() {
  final list = <int>[]; // Explicitly typed
  list.add(1);
  list.add(2);
  printInts(list);
}
```

### Example: Fixing Method Overrides (Contravariance)
**Input (Fails Static Analysis):**
```dart
class Animal {
  void chase(Animal a) {}
}

class Cat extends Animal {
  @override
  void chase(Mouse a) {} // Error: Tightening parameter type
}
```

**Output (Passes Static Analysis):**
```dart
class Animal {
  void chase(Animal a) {}
}

class Cat extends Animal {
  @override
  void chase(covariant Mouse a) {} // Explicitly marked covariant
}
```

### Example: Fixing Null Safety with `late`
**Input (Fails Static Analysis):**
```dart
class Thermometer {
  String temperature; // Error: Non-nullable instance field must be initialized

  void read() {
    temperature = '20C';
  }
}
```

**Output (Passes Static Analysis):**
```dart
class Thermometer {
  late String temperature; // Defers initialization check to runtime

  void read() {
    temperature = '20C';
  }
}
```
