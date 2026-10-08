import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/app.dart';

void main() {
  testWidgets('launches the app shell', (tester) async {
    await tester.pumpWidget(const SLFurnituresApp());

    expect(find.byType(MaterialApp), findsOneWidget);
    expect(find.text('SL Furnitures'), findsOneWidget);
  });
}
