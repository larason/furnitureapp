import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:go_router/go_router.dart';

import '../../../core/presentation/app_inline_error.dart';
import '../../../core/presentation/error_presentation_mapper.dart';
import '../../../navigation/app_routes.dart';
import '../../../theme/app_media.dart';
import '../../../theme/app_spacing.dart';
import '../data/catalog_repository.dart';
import 'catalog_controller.dart';
import 'product_card.dart';

/// Reusable paginated product listing.
///
/// Owns the catalog grid, inline refresh and pagination failures, and stable
/// per-product keys, so the catalog, home, and category landing screens cannot
/// drift apart. Server ordering and pagination stay authoritative: a page is
/// requested only when the server reports `has_next`, and a failed request
/// keeps the products that are already on screen.
class ProductGridSliver extends StatelessWidget {
  const ProductGridSliver({
    super.key,
    required this.controller,
    required this.page,
    this.refreshError,
  });

  final CatalogController controller;
  final CatalogPage page;
  final ErrorPresentation? refreshError;

  @override
  Widget build(BuildContext context) {
    final hasMore = page.pagination.hasNext || controller.nextPageError != null;
    return SliverPadding(
      padding: const EdgeInsets.all(AppSpacing.gutterPhone),
      sliver: SliverMainAxisGroup(
        slivers: <Widget>[
          if (refreshError case final error?) _refreshFailure(error),
          SliverLayoutBuilder(builder: _grid),
          if (hasMore) _pagination(),
        ],
      ),
    );
  }

  Widget _refreshFailure(ErrorPresentation error) => SliverToBoxAdapter(
    child: Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.space4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          AppInlineError(error: error),
          TextButton(
            onPressed: controller.refresh,
            child: const Text('Refresh'),
          ),
        ],
      ),
    ),
  );

  Widget _grid(BuildContext context, SliverConstraints constraints) {
    final textScale = MediaQuery.textScalerOf(context).scale(1);
    final columns = constraints.crossAxisExtent >= AppSpacing.breakpointTablet
        ? 3
        : constraints.crossAxisExtent >= AppSpacing.breakpointPhone
        ? 2
        : 1;
    return SliverGrid.builder(
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: columns,
        mainAxisSpacing: AppSpacing.space6,
        crossAxisSpacing: AppSpacing.space4,
        childAspectRatio: _childAspectRatio(
          constraints.crossAxisExtent,
          columns,
          textScale,
        ),
      ),
      itemCount: page.products.length,
      itemBuilder: (context, index) {
        final product = page.products[index];
        return ProductCard(
          key: ValueKey<String>(product.id),
          product: product,
          onTap: () => context.push(AppRoutes.product(product.slug)),
        );
      },
    );
  }

  Widget _pagination() => SliverToBoxAdapter(
    child: Padding(
      padding: const EdgeInsets.only(top: AppSpacing.space6),
      child: switch (controller.nextPageError) {
        final error? => Column(
          children: <Widget>[
            AppInlineError(error: error),
            TextButton(
              onPressed: controller.loadNextPage,
              child: const Text('Try again'),
            ),
          ],
        ),
        null => Center(
          child: FilledButton(
            onPressed: controller.loadNextPage,
            child: const Text('Load more'),
          ),
        ),
      },
    ),
  );

  double _childAspectRatio(double width, int columns, double textScale) {
    final tileWidth = (width - (AppSpacing.space4 * (columns - 1))) / columns;
    final imageHeight = tileWidth / AppMedia.productCard;
    // Product names are allowed two lines; reserve both lines so narrow
    // screens do not clip the card metadata below the photograph.
    final textHeight = 92 * textScale + AppSpacing.space6;
    return tileWidth / (imageHeight + textHeight);
  }
}
