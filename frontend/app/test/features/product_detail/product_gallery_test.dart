import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/features/product_detail/data/product_detail.dart';
import 'package:sl_furnitures/features/product_detail/presentation/widgets/product_gallery.dart';
import 'package:sl_furnitures/theme/app_media.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

void main() {
  testWidgets('renders a single image without a position indicator', (
    tester,
  ) async {
    await tester.pumpWidget(_app(_images(1)));

    expect(find.text('Showing image 1 of 1'), findsNothing);
    expect(find.byType(PageView), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('announces the position and swipes between images', (
    tester,
  ) async {
    await tester.pumpWidget(_app(_images(3)));
    await tester.pumpAndSettle();

    expect(find.text('Showing image 1 of 3'), findsOneWidget);

    await tester.fling(find.byType(PageView), const Offset(-400, 0), 1200);
    await tester.pumpAndSettle();

    expect(find.text('Showing image 2 of 3'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('opens on the image the contract marks primary', (tester) async {
    await tester.pumpWidget(
      _app(<ProductDetailImage>[
        _image('a', sortOrder: 1),
        _image('b', sortOrder: 2, isPrimary: true),
        _image('c', sortOrder: 3),
      ], initialIndex: 1),
    );
    await tester.pumpAndSettle();

    expect(find.text('Showing image 2 of 3'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('describes each photograph once for assistive technology', (
    tester,
  ) async {
    final handle = tester.ensureSemantics();
    await tester.pumpWidget(_app(_images(2)));
    await tester.pumpAndSettle();

    expect(find.bySemanticsLabel('Front view'), findsOneWidget);
    expect(
      find.bySemanticsLabel('Showing image 1 of 2'),
      findsOneWidget,
      reason: 'The position indicator owns one announcement.',
    );
    expect(
      find.bySemanticsLabel('Angle view'),
      findsNothing,
      reason: 'Off-screen photographs must not be announced.',
    );

    await tester.fling(find.byType(PageView), const Offset(-400, 0), 1200);
    await tester.pumpAndSettle();

    expect(find.bySemanticsLabel('Angle view'), findsOneWidget);
    expect(find.bySemanticsLabel('Front view'), findsNothing);
    handle.dispose();
  });

  testWidgets('keeps a stable frame when no photography was published', (
    tester,
  ) async {
    await tester.pumpWidget(_app(const <ProductDetailImage>[]));

    expect(find.byType(PageView), findsNothing);
    expect(
      find.bySemanticsLabel('No product photograph available'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('falls back when a photograph cannot be decoded', (tester) async {
    await tester.pumpWidget(
      _app(<ProductDetailImage>[
        ProductDetailImage(
          id: 'broken',
          url: '',
          altText: 'Broken photograph',
          sortOrder: 0,
          isPrimary: true,
          assetPath: 'assets/furnitures/fixtures/products/missing.jpg',
        ),
      ]),
    );
    await tester.pumpAndSettle();

    expect(find.byType(Image), findsOneWidget);
    expect(find.byIcon(Icons.chair_outlined), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('holds the canonical hero aspect ratio', (tester) async {
    await tester.pumpWidget(_app(_images(2)));

    final gallery = tester.getSize(find.byType(PageView));
    expect(gallery.width / gallery.height, closeTo(AppMedia.productHero, 0.01));
  });

  testWidgets('never announces a position the gallery no longer has', (
    tester,
  ) async {
    await tester.pumpWidget(_app(_images(3), initialIndex: 2));
    await tester.pumpAndSettle();
    expect(find.text('Showing image 3 of 3'), findsOneWidget);

    await tester.pumpWidget(_app(_images(1)));
    await tester.pumpAndSettle();

    expect(find.text('Showing image 3 of 3'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('keeps the viewer on the current page when the same product '
      'refreshes', (tester) async {
    await tester.pumpWidget(_app(_images(3)));
    await tester.pumpAndSettle();
    await tester.drag(find.byType(PageView), const Offset(-500, 0));
    await tester.pumpAndSettle();
    expect(find.text('Showing image 2 of 3'), findsOneWidget);

    await tester.pumpWidget(_app(_images(3)));
    await tester.pumpAndSettle();

    expect(
      find.text('Showing image 2 of 3'),
      findsOneWidget,
      reason: 'A refresh returning the same photos must not reset the page.',
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('schedules no looping animation under reduced motion', (
    tester,
  ) async {
    await tester.pumpWidget(_app(_images(2), disableAnimations: true));
    await tester.pumpAndSettle();

    expect(tester.binding.hasScheduledFrame, isFalse);
    expect(tester.takeException(), isNull);
  });
}

List<ProductDetailImage> _images(int count) => <ProductDetailImage>[
  for (var index = 0; index < count; index++)
    _image(
      String.fromCharCode(97 + index),
      sortOrder: index,
      altText: index == 0 ? 'Front view' : 'Angle view',
      isPrimary: index == 0,
    ),
];

ProductDetailImage _image(
  String id, {
  int sortOrder = 0,
  String altText = 'Front view',
  bool isPrimary = false,
}) => ProductDetailImage(
  id: 'img_$id',
  url: 'https://cdn.example.test/$id.webp',
  altText: altText,
  sortOrder: sortOrder,
  isPrimary: isPrimary,
);

Widget _app(
  List<ProductDetailImage> images, {
  int initialIndex = 0,
  bool disableAnimations = false,
}) => MaterialApp(
  theme: AppTheme.light(),
  home: Builder(
    builder: (context) => MediaQuery(
      data: MediaQuery.of(
        context,
      ).copyWith(disableAnimations: disableAnimations),
      child: Scaffold(
        body: ProductGallery(images: images, initialIndex: initialIndex),
      ),
    ),
  ),
);
