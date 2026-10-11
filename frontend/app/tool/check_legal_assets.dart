import 'dart:io';

void main() {
  const pairs = <(String, String)>[
    ('../../privacy-policy.txt', 'assets/legal/privacy-policy.txt'),
    ('../../terms-of-service.txt', 'assets/legal/terms-of-service.txt'),
  ];

  for (final (sourcePath, assetPath) in pairs) {
    final source = File(sourcePath);
    final asset = File(assetPath);
    if (!source.existsSync() || !asset.existsSync()) {
      throw StateError(
        'Missing legal source or asset: $sourcePath / $assetPath',
      );
    }
    if (!_sameBytes(source.readAsBytesSync(), asset.readAsBytesSync())) {
      throw StateError('Legal asset differs from its source: $sourcePath');
    }
  }
  stdout.writeln('Legal assets match their authoritative sources.');
}

bool _sameBytes(List<int> left, List<int> right) {
  if (left.length != right.length) return false;
  for (var index = 0; index < left.length; index++) {
    if (left[index] != right[index]) return false;
  }
  return true;
}
