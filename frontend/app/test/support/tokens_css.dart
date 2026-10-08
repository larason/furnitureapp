import 'dart:io';

/// Locates the canonical token source regardless of the working directory the
/// test runner was invoked from.
File canonicalTokensCss() {
  const candidates = <String>[
    '../design-system/tokens.css',
    'design-system/tokens.css',
  ];
  for (final path in candidates) {
    final file = File(path);
    if (file.existsSync()) return file;
  }
  throw StateError('tokens.css not found from ${Directory.current.path}');
}
