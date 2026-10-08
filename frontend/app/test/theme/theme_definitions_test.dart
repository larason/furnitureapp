import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

/// The generated token file is the only place raw design values may live.
const String _generatedTokenFile = 'tokens/generated_tokens.dart';

/// [Directory.listSync] reports the host separator, so normalise before the
/// path comparisons below. They assume POSIX separators and would otherwise
/// misread paths on Windows.
String _posixPath(String path) => path.replaceAll(r'\', '/');

void main() {
  test('theme definitions introduce no raw colour literals', () {
    final offenders = <String>[];
    for (final entity in Directory('lib/theme').listSync(recursive: true)) {
      if (entity is! File) continue;
      final path = _posixPath(entity.path);
      if (!path.endsWith('.dart')) continue;
      if (path.endsWith(_generatedTokenFile)) continue;
      if (RegExp(r'Color\(0x').hasMatch(entity.readAsStringSync())) {
        offenders.add(path);
      }
    }
    expect(
      offenders,
      isEmpty,
      reason: 'Raw colours must come from GeneratedTokens: $offenders',
    );
  });

  test('only the token generator and theme consume the generated file', () {
    final consumers = <String>[];
    for (final entity in Directory('lib').listSync(recursive: true)) {
      if (entity is! File) continue;
      final path = _posixPath(entity.path);
      if (!path.endsWith('.dart')) continue;
      if (path.endsWith(_generatedTokenFile)) continue;
      if (entity.readAsStringSync().contains('generated_tokens.dart')) {
        consumers.add(path);
      }
    }
    expect(consumers, isNotEmpty);
    expect(
      consumers.every((path) => path.contains('/theme/')),
      isTrue,
      reason: 'Generated tokens must stay inside the theme adapter: $consumers',
    );
  });

  testWidgets('foundational Material 3 components render under the theme', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        home: Scaffold(
          appBar: AppBar(title: const Text('SL Furnitures')),
          body: ListView(
            children: <Widget>[
              const Text('Furniture that feels like home.'),
              FilledButton(onPressed: () {}, child: const Text('Request')),
              OutlinedButton(onPressed: () {}, child: const Text('Details')),
              TextButton(onPressed: () {}, child: const Text('Quiet')),
              const TextField(
                decoration: InputDecoration(
                  labelText: 'Name',
                  helperText: 'Helper',
                ),
              ),
              const Card(child: ListTile(title: Text('Card'))),
              const LinearProgressIndicator(value: 0.5),
              const Divider(),
            ],
          ),
          bottomNavigationBar: NavigationBar(
            selectedIndex: 0,
            onDestinationSelected: (_) {},
            destinations: const <Widget>[
              NavigationDestination(icon: Icon(Icons.home), label: 'Home'),
              NavigationDestination(icon: Icon(Icons.search), label: 'Search'),
            ],
          ),
        ),
      ),
    );

    expect(tester.takeException(), isNull);
    expect(find.text('SL Furnitures'), findsOneWidget);
    expect(find.text('Furniture that feels like home.'), findsOneWidget);
  });
}
