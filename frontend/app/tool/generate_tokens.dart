/// Regenerates the Flutter design-token adapter from the canonical CSS.
///
/// Usage:
///   dart run tool/generate_tokens.dart           # write generated tokens
///   dart run tool/generate_tokens.dart --check   # fail if tokens are stale
library;

import 'dart:io';

import 'token_generator.dart';

const String sourceRelativePath = '../design-system/tokens.css';
const String outputRelativePath = 'lib/theme/tokens/generated_tokens.dart';
const String sourceLabel = 'frontend/design-system/tokens.css';

Future<void> main(List<String> arguments) async {
  exitCode = await _run(arguments.contains('--check'));
}

Future<int> _run(bool check) async {
  final appRoot = File(Platform.script.toFilePath()).parent.parent;
  final sourceFile = File('${appRoot.path}/$sourceRelativePath');
  final outputFile = File('${appRoot.path}/$outputRelativePath');

  if (!sourceFile.existsSync()) {
    stderr.writeln('Canonical token source not found: ${sourceFile.path}');
    return 1;
  }

  final generated = _generate(sourceFile);
  if (generated == null) return 1;

  return check
      ? await _verify(outputFile, generated)
      : await _write(outputFile, generated);
}

String? _generate(File sourceFile) {
  try {
    return generateTokensDart(
      sourceFile.readAsStringSync(),
      sourceLabel: sourceLabel,
    );
  } on TokenGenerationException catch (error) {
    stderr.writeln(error.message);
    return null;
  }
}

Future<int> _write(File outputFile, String generated) async {
  outputFile.parent.createSync(recursive: true);
  outputFile.writeAsStringSync(generated);
  final formatted = await formatDart(outputFile.path);
  if (formatted != null) outputFile.writeAsStringSync(formatted);
  stdout.writeln('Wrote ${outputFile.path}');
  return 0;
}

Future<int> _verify(File outputFile, String generated) async {
  if (!outputFile.existsSync()) {
    stderr.writeln('Generated tokens are missing: ${outputFile.path}');
    return 1;
  }
  final expected = await formattedDartSource(generated);
  if (expected != null && outputFile.readAsStringSync() == expected) {
    stdout.writeln('Token generation: OK (generated tokens are current).');
    return 0;
  }
  stderr.writeln(
    'Generated tokens are stale. Run: dart run tool/generate_tokens.dart',
  );
  return 1;
}

/// Formats a candidate output in a scratch file so write and check modes apply
/// exactly the same formatter the repository uses.
Future<String?> formattedDartSource(String source) async {
  final scratch = File(
    '${Directory.systemTemp.path}/slf_tokens_${DateTime.now().microsecondsSinceEpoch}.dart',
  );
  try {
    scratch.writeAsStringSync(source);
    return await formatDart(scratch.path);
  } finally {
    if (scratch.existsSync()) scratch.deleteSync();
  }
}

/// Runs the Dart formatter on [path]. Returns the formatted source, or null when
/// the formatter is unavailable or reports a failure.
Future<String?> formatDart(String path) async {
  final result = await Process.run(Platform.resolvedExecutable, <String>[
    'format',
    path,
  ]);
  if (result.exitCode != 0) {
    stderr.writeln('dart format failed for $path: ${result.stderr}');
    return null;
  }
  return File(path).readAsStringSync();
}
