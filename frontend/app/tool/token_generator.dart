/// Deterministic design-token generator for the SL Furnitures Flutter app.
///
/// The canonical authority is `frontend/design-system/tokens.css`. This library
/// converts that CSS into a typed Dart representation so the Flutter theme is
/// never a second, hand-maintained palette.
///
/// The generator is intentionally small: `tokens.css` uses plain custom
/// properties with `var()` references, so a full CSS parser is not required.
library;

/// Raised when the canonical token source cannot be converted safely.
class TokenGenerationException implements Exception {
  TokenGenerationException(this.message);

  final String message;

  @override
  String toString() => 'TokenGenerationException: $message';
}

/// Tokens the Flutter theme depends on. A missing token is a hard failure
/// rather than a silent default.
const List<String> requiredTokenNames = <String>[
  'color-neutral-950',
  'color-neutral-700',
  'color-neutral-500',
  'color-neutral-300',
  'color-neutral-200',
  'color-white',
  'color-warm-100',
  'color-warm-200',
  'color-brand-900',
  'color-success',
  'color-warning',
  'color-danger',
  'color-info',
  'color-focus',
  'surface-canvas',
  'surface-paper',
  'surface-editorial',
  'surface-inverse',
  'text-primary',
  'text-secondary',
  'text-muted',
  'text-inverse',
  'border-subtle',
  'border-default',
  'border-strong',
  'action-primary',
  'action-primary-hover',
  'action-primary-active',
  'action-primary-disabled',
  'action-secondary',
  'action-focus',
  'accent-brand',
  'accent-material',
  'font-display',
  'font-ui',
  'font-weight-regular',
  'font-weight-medium',
  'text-xs',
  'text-sm',
  'text-base',
  'text-lg',
  'text-xl',
  'text-2xl',
  'text-3xl',
  'text-4xl',
  'leading-body',
  'leading-snug',
  'leading-display',
  'tracking-display',
  'space-1',
  'space-2',
  'space-3',
  'space-4',
  'space-5',
  'space-6',
  'space-7',
  'space-8',
  'space-9',
  'space-10',
  'radius-sm',
  'radius-md',
  'radius-lg',
  'radius-pill',
  'elev-ring',
  'elev-raised',
  'focus-ring',
  'motion-fast',
  'motion-base',
  'ease-standard',
  'breakpoint-phone',
  'breakpoint-tablet',
  'breakpoint-desktop',
];

final RegExp _commentOrDeclaration = RegExp(
  r'/\*\s*([^*]+?)\s*\*/|--([a-zA-Z0-9-]+)\s*:\s*([^;]+);',
);
final RegExp _varReference = RegExp(r'var\(\s*--([a-zA-Z0-9-]+)\s*\)');
final RegExp _hexColor = RegExp(r'^#[0-9a-fA-F]{3,8}$');
final RegExp _rgbColor = RegExp(r'^rgba?\([^)]*\)$');
final RegExp _cubicBezier = RegExp(r'^cubic-bezier\(([^)]*)\)$');
final RegExp _duration = RegExp(r'^([0-9.]+)ms$');
final RegExp _pixels = RegExp(r'^([0-9.]+)px$');
final RegExp _characters = RegExp(r'^([0-9.]+)ch$');
final RegExp _ratio = RegExp(r'^([0-9.]+)\s*/\s*([0-9.]+)$');
final RegExp _integer = RegExp(r'^-?[0-9]+$');
final RegExp _decimal = RegExp(r'^-?[0-9]*\.?[0-9]+$');

/// CSS allows unitless zero, so every length keeps `px` optional.
final RegExp _shadow = RegExp(
  r'^(-?[0-9.]+)(?:px)?\s+(-?[0-9.]+)(?:px)?\s+(-?[0-9.]+)(?:px)?'
  r'(?:\s+(-?[0-9.]+)(?:px)?)?\s+(\S.*)$',
);

/// Generates the Dart source for [css], or throws [TokenGenerationException].
String generateTokensDart(
  String css, {
  String sourceLabel = 'frontend/design-system/tokens.css',
}) {
  final body = _extractRootBody(css);
  final raw = _parseDeclarations(body);
  final resolved = _resolveAll(raw);
  _assertRequiredTokens(resolved);
  return _emit(resolved, body, sourceLabel);
}

String _extractRootBody(String css) {
  final start = css.indexOf(':root');
  if (start < 0) {
    throw TokenGenerationException(
      'Canonical tokens must declare a :root block.',
    );
  }
  final open = css.indexOf('{', start);
  if (open < 0) {
    throw TokenGenerationException(
      'The :root block is missing an opening brace.',
    );
  }
  var depth = 0;
  for (var index = open; index < css.length; index++) {
    final character = css[index];
    if (character == '{') depth++;
    if (character == '}') {
      depth--;
      if (depth == 0) return css.substring(open + 1, index);
    }
  }
  throw TokenGenerationException('The :root block is not closed.');
}

Map<String, String> _parseDeclarations(String body) {
  final declarations = <String, String>{};
  for (final match in _commentOrDeclaration.allMatches(body)) {
    final name = match.group(2);
    if (name == null) continue;
    declarations[name] = match.group(3)!.trim();
  }
  if (declarations.isEmpty) {
    throw TokenGenerationException('No custom properties were found in :root.');
  }
  return declarations;
}

Map<String, String> _resolveAll(Map<String, String> raw) {
  final resolved = <String, String>{};
  for (final name in raw.keys) {
    resolved[name] = _resolveToken(name, raw, const <String>[]);
  }
  return resolved;
}

String _resolveToken(String name, Map<String, String> raw, List<String> stack) {
  if (stack.contains(name)) {
    throw TokenGenerationException(
      'Circular token reference: ${<String>[...stack, name].join(' -> ')}',
    );
  }
  final value = raw[name];
  if (value == null) {
    throw TokenGenerationException('Unresolved token reference --$name.');
  }
  return _resolveReferences(value, raw, <String>[...stack, name]);
}

String _resolveReferences(
  String value,
  Map<String, String> raw,
  List<String> stack,
) {
  if (!_varReference.hasMatch(value)) return value;
  return value.replaceAllMapped(
    _varReference,
    (match) => _resolveToken(match.group(1)!, raw, stack),
  );
}

void _assertRequiredTokens(Map<String, String> resolved) {
  final missing = requiredTokenNames
      .where((name) => !resolved.containsKey(name))
      .toList();
  if (missing.isNotEmpty) {
    throw TokenGenerationException(
      'Missing required tokens: ${missing.map((n) => '--$n').join(', ')}',
    );
  }
}

String _emit(Map<String, String> resolved, String body, String sourceLabel) {
  final buffer = StringBuffer()
    ..writeln('// GENERATED FILE - DO NOT EDIT.')
    ..writeln('//')
    ..writeln('// Source: $sourceLabel (canonical token authority).')
    ..writeln('// Regenerate: dart run tool/generate_tokens.dart')
    ..writeln('// Verify freshness: dart run tool/generate_tokens.dart --check')
    ..writeln('//')
    ..writeln(
      '// This file is a derived, typed representation of the canonical',
    )
    ..writeln('// design tokens. Edit tokens.css and regenerate instead.')
    ..writeln()
    ..writeln("import 'dart:ui';")
    ..writeln()
    ..writeln(
      '/// Typed Flutter representation of the canonical design tokens.',
    )
    ..writeln('abstract final class GeneratedTokens {');

  final emittedNames = <String>{};
  var hasContent = false;
  for (final match in _commentOrDeclaration.allMatches(body)) {
    final comment = match.group(1);
    if (comment != null) {
      if (hasContent) buffer.writeln();
      buffer.writeln('  // ${comment.trim()}');
      hasContent = true;
      continue;
    }
    hasContent = true;
    final name = match.group(2)!;
    final value = resolved[name]!;
    for (final line in _emitToken(name, value)) {
      final identifier = _identifierOf(line);
      if (identifier != null && !emittedNames.add(identifier)) {
        throw TokenGenerationException(
          'Duplicate Dart identifier "$identifier" from --$name.',
        );
      }
      buffer.writeln(line);
    }
  }

  buffer.writeln('}');
  return buffer.toString();
}

String? _identifierOf(String line) {
  final match = RegExp(r'^  static const \S+ (\w+) =').firstMatch(line);
  return match?.group(1);
}

List<String> _emitToken(String name, String value) {
  if (value == 'none') return const <String>[];
  final identifier = dartIdentifier(name);
  if (_shadow.hasMatch(value)) return _emitShadow(identifier, value);
  if (_cubicBezier.hasMatch(value)) return _emitCubicBezier(identifier, value);
  if (_isColor(value)) {
    return <String>[
      '  static const Color $identifier = ${_colorLiteral(value)};',
    ];
  }
  if (_isFontWeight(name, value)) {
    return <String>[
      '  static const FontWeight $identifier = FontWeight.w$value;',
    ];
  }
  if (_isFontStack(name)) return _emitFontStack(identifier, value);
  final duration = _duration.firstMatch(value);
  if (duration != null) {
    return <String>[
      '  static const Duration $identifier = '
          'Duration(milliseconds: ${_numberLiteral(duration.group(1)!)});',
    ];
  }
  if (_ratio.hasMatch(value)) {
    return <String>['  static const double $identifier = ${value.trim()};'];
  }
  final pixels = _pixels.firstMatch(value);
  if (pixels != null) {
    return <String>[
      '  static const double $identifier = ${_numberLiteral(pixels.group(1)!)};',
    ];
  }
  final characters = _characters.firstMatch(value);
  if (characters != null) {
    return <String>[
      '  // Source unit: ${characters.group(0)} (approximate in Flutter).',
      '  static const double $identifier = ${_numberLiteral(characters.group(1)!)};',
    ];
  }
  if (_integer.hasMatch(value)) return _emitInteger(name, identifier, value);
  if (_decimal.hasMatch(value)) {
    return <String>[
      '  static const double $identifier = ${_numberLiteral(value)};',
    ];
  }
  throw TokenGenerationException('Unsupported value for --$name: "$value".');
}

bool _isColor(String value) =>
    _hexColor.hasMatch(value) ||
    _rgbColor.hasMatch(value) ||
    value == 'transparent';

bool _isFontWeight(String name, String value) =>
    name.startsWith('font-weight-') && _integer.hasMatch(value);

bool _isFontStack(String name) =>
    name.startsWith('font-') && !name.startsWith('font-weight-');

/// Z-index values are the only unitless integers Flutter needs as `int`.
/// Everything else unitless (for example letter spacing) is a `double`.
List<String> _emitInteger(String name, String identifier, String value) =>
    name.startsWith('z-')
    ? <String>['  static const int $identifier = $value;']
    : <String>['  static const double $identifier = $value;'];

List<String> _emitFontStack(String identifier, String value) {
  final families = value
      .split(',')
      .map((part) => part.trim())
      .where((part) => part.isNotEmpty)
      .map(_stringLiteral)
      .toList();
  final singleLine =
      '  static const List<String> $identifier = <String>[${families.join(', ')}];';
  // Mirror the Dart formatter: wrap only when the single line would not fit.
  if (singleLine.length <= _maxLineLength) return <String>[singleLine];
  return <String>[
    '  static const List<String> $identifier = <String>[',
    for (final family in families) '    $family,',
    '  ];',
  ];
}

const int _maxLineLength = 80;

String _stringLiteral(String value) {
  final unquoted = value.replaceAll('"', '').replaceAll("'", '');
  return "'$unquoted'";
}

List<String> _emitShadow(String identifier, String value) {
  final match = _shadow.firstMatch(value)!;
  final lines = <String>[
    '  static const double ${identifier}OffsetX = ${_numberLiteral(match.group(1)!)};',
    '  static const double ${identifier}OffsetY = ${_numberLiteral(match.group(2)!)};',
    '  static const double ${identifier}Blur = ${_numberLiteral(match.group(3)!)};',
  ];
  final spread = match.group(4);
  if (spread != null) {
    lines.add(
      '  static const double ${identifier}Spread = ${_numberLiteral(spread)};',
    );
  }
  lines.add(
    '  static const Color ${identifier}Color = ${_colorLiteral(match.group(5)!)};',
  );
  return lines;
}

List<String> _emitCubicBezier(String identifier, String value) {
  final points = _cubicBezier
      .firstMatch(value)!
      .group(1)!
      .split(',')
      .map((part) => part.trim())
      .toList();
  if (points.length != 4) {
    throw TokenGenerationException('Unsupported cubic-bezier value: "$value".');
  }
  const suffixes = <String>['X1', 'Y1', 'X2', 'Y2'];
  return <String>[
    for (var index = 0; index < points.length; index++)
      '  static const double $identifier${suffixes[index]} = '
          '${_numberLiteral(points[index])};',
  ];
}

String dartIdentifier(String tokenName) {
  final parts = tokenName.split('-');
  final buffer = StringBuffer(parts.first);
  for (final part in parts.skip(1)) {
    buffer.write(part.isEmpty ? '' : part[0].toUpperCase() + part.substring(1));
  }
  return buffer.toString();
}

/// Preserves the canonical CSS numeric literal so generated output is stable
/// and does not lose precision through a round trip.
String _numberLiteral(String value) => value.trim();

String _colorLiteral(String value) {
  if (value == 'transparent') return 'Color(0x00000000)';
  final rgb = _rgbColor.firstMatch(value);
  if (rgb != null) return _rgbLiteral(value);
  return 'Color(0x${_hexToFlutter(value)})';
}

/// Converts CSS `#RGB`, `#RGBA`, `#RRGGBB`, or `#RRGGBBAA` into Flutter's
/// `0xAARRGGBB` literal order: CSS keeps alpha last, Flutter keeps it first.
String _hexToFlutter(String value) {
  final hex = value.replaceFirst('#', '');
  final digits = switch (hex.length) {
    3 || 4 => hex.split('').map((character) => '$character$character').join(),
    6 || 8 => hex,
    _ => throw TokenGenerationException('Unsupported color value: "$value".'),
  };
  final argb = digits.length == 8
      ? '${digits.substring(6)}${digits.substring(0, 6)}'
      : 'FF$digits';
  return argb.toUpperCase();
}

String _rgbLiteral(String value) {
  final parts = value
      .replaceFirst(RegExp(r'^rgba?\('), '')
      .replaceFirst(RegExp(r'\)$'), '')
      .split(',')
      .map((part) => part.trim())
      .toList();
  if (parts.length < 3) {
    throw TokenGenerationException('Unsupported rgb color value: "$value".');
  }
  final red = int.parse(parts[0]);
  final green = int.parse(parts[1]);
  final blue = int.parse(parts[2]);
  final alpha = parts.length > 3 ? (double.parse(parts[3]) * 255).round() : 255;
  return 'Color(0x${_byte(alpha)}${_byte(red)}${_byte(green)}${_byte(blue)})';
}

String _byte(int value) =>
    value.toRadixString(16).padLeft(2, '0').toUpperCase();
