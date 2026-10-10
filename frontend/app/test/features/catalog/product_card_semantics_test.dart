import 'package:flutter/material.dart';
import 'package:flutter/semantics.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/features/catalog/data/product_summary.dart';
import 'package:sl_furnitures/features/catalog/presentation/product_card.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

void main() {
  const product = ProductSummary(
    id: 'prod_01h8x8a1b2c3d4e5f6g7h8j9',
    slug: 'lounge-chair',
    name: 'Lounge chair',
    productType: 'MADE_TO_ORDER',
    price: Money(amount: 10000000, currency: 'TZS'),
    category: ProductCategorySummary(
      id: 'cat_living',
      slug: 'living-room',
      name: 'Living Room',
    ),
    primaryImage: ProductImageSummary(
      id: 'img_living_room_1',
      url: 'https://cdn.example.test/products/lounge-chair.jpg',
      altText: 'An open wooden armchair with cream seat and back cushions',
    ),
    availability: 'available',
    stockIndicator: 'MADE_TO_ORDER',
  );

  Widget card({required VoidCallback onTap}) => MaterialApp(
    theme: AppTheme.light(),
    home: Scaffold(
      body: SizedBox(
        width: 380,
        height: 400,
        child: ProductCard(product: product, onTap: onTap),
      ),
    ),
  );

  group('ProductCard semantics', () {
    testWidgets('announces one complete label instead of every child', (
      tester,
    ) async {
      final handle = tester.ensureSemantics();
      await tester.pumpWidget(card(onTap: () {}));

      final node = tester.getSemantics(find.byType(ProductCard));

      expect(
        node.label,
        'Lounge chair, made to order, TZS 100,000',
        reason:
            'The card already carries the product name, price, and fulfilment '
            'state. Repeating the child text and image alt text would make a '
            'screen reader announce the same facts twice.',
      );
      expect(
        node.label,
        isNot(contains('An open wooden armchair')),
        reason: 'Image alt text must not be appended to the control label.',
      );
      handle.dispose();
    });

    testWidgets('exposes a tap action and activates the card', (tester) async {
      final handle = tester.ensureSemantics();
      var taps = 0;
      await tester.pumpWidget(card(onTap: () => taps++));

      final node = tester.getSemantics(find.byType(ProductCard));
      expect(
        node.getSemanticsData().hasAction(SemanticsAction.tap),
        isTrue,
        reason: 'A labelled control must remain operable without a pointer.',
      );

      tester.semantics.performAction(
        find.semantics.byLabel('Lounge chair, made to order, TZS 100,000'),
        SemanticsAction.tap,
      );
      await tester.pump();

      expect(taps, 1);
      handle.dispose();
    });

    testWidgets('keeps the pointer tap working alongside semantics', (
      tester,
    ) async {
      var taps = 0;
      await tester.pumpWidget(card(onTap: () => taps++));

      await tester.tap(find.byType(ProductCard));
      await tester.pump();

      expect(
        taps,
        1,
        reason: 'Hiding redundant semantics must not disable pointer input.',
      );
    });
  });
}
