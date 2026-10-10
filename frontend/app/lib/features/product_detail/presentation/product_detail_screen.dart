import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/presentation/app_empty_view.dart';
import '../../../core/presentation/app_error_view.dart';
import '../../../core/presentation/app_inline_error.dart';
import '../../../core/presentation/app_loading_view.dart';
import '../../../core/presentation/async_view_state.dart';
import '../../../core/presentation/error_presentation_mapper.dart';
import '../../../navigation/app_routes.dart';
import '../../../theme/app_spacing.dart';
import '../data/product_detail.dart';
import '../data/product_detail_repository.dart';
import 'product_detail_controller.dart';
import 'widgets/product_gallery.dart';
import 'widgets/product_information.dart';
import 'widgets/product_request_action.dart';

/// Public product detail page (`CAT-002`).
///
/// The page shows one product: photography, identity, price, availability,
/// description, the embedded options, and the request-first action. It is
/// available anonymously in every Clerk state and never starts an auth flow.
///
/// A missing or non-public product becomes an unavailable state that returns to
/// the catalog; it never reveals whether an unpublished product exists. A new
/// identifier rebuilds the controller, so a previous product can never appear
/// under a newly selected one.
class ProductDetailScreen extends StatefulWidget {
  const ProductDetailScreen({
    super.key,
    required this.identifier,
    required this.repository,
  });

  final String identifier;
  final ProductDetailRepository repository;

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  late ProductDetailController _controller = _createController();

  ProductDetailController _createController() => ProductDetailController(
    repository: widget.repository,
    identifier: widget.identifier,
  )..load();

  @override
  void didUpdateWidget(ProductDetailScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.identifier == widget.identifier) return;
    final previous = _controller;
    _controller = _createController();
    previous.dispose();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: _controller,
    builder: (context, _) {
      final state = _controller.state;
      final product = _controller.product;
      return Scaffold(
        appBar: AppBar(
          title: Text(
            product?.name ?? 'Furniture',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
        body: switch (state) {
          AsyncInitial() || AsyncLoading(previousData: null) =>
            const AppLoadingView(message: 'Loading furniture'),
          AsyncFailure(previousData: null, :final error) => _failure(error),
          AsyncContent(:final data) ||
          AsyncRefreshing(:final data) ||
          AsyncLoading(previousData: final data?) => _detail(data, null),
          AsyncFailure(previousData: final data?, :final error) => _detail(
            data,
            error,
          ),
          _ => const SizedBox.shrink(),
        },
      );
    },
  );

  Widget _failure(ErrorPresentation error) {
    if (!_controller.productUnavailable) {
      return AppErrorView(error: error, onRecovery: _controller.load);
    }
    return AppEmptyView(
      title: 'This furniture is unavailable',
      description: 'It may have been moved or is no longer published.',
      icon: Icons.chair_outlined,
      actionLabel: 'Browse furniture',
      onAction: () => context.go(AppRoutes.products),
    );
  }

  Widget _detail(ProductDetail product, ErrorPresentation? refreshError) =>
      RefreshIndicator(
        onRefresh: _controller.refresh,
        child: LayoutBuilder(
          builder: (context, constraints) => SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight),
              child: _content(context, product, refreshError, constraints),
            ),
          ),
        ),
      );

  /// Editorial compositions differ: a phone stacks photography above the
  /// information, while a wide window places them side by side. Both use the
  /// same canonical spacing and media tokens.
  Widget _content(
    BuildContext context,
    ProductDetail product,
    ErrorPresentation? refreshError,
    BoxConstraints constraints,
  ) {
    final information = _information(context, product, refreshError);
    final gallery = ProductGallery(
      key: ValueKey<String>('product_detail.gallery.${product.id}'),
      images: product.images,
      initialIndex: product.initialImageIndex,
    );
    if (constraints.maxWidth < AppSpacing.breakpointTablet) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[gallery, _padded(information)],
      );
    }
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Expanded(
          flex: 3,
          child: Padding(
            padding: const EdgeInsetsDirectional.only(
              start: AppSpacing.gutterPhone,
            ),
            child: gallery,
          ),
        ),
        Expanded(flex: 2, child: _padded(information)),
      ],
    );
  }

  Widget _padded(Widget child) => Padding(
    padding: const EdgeInsets.fromLTRB(
      AppSpacing.gutterPhone,
      AppSpacing.space6,
      AppSpacing.gutterPhone,
      AppSpacing.space8,
    ),
    child: child,
  );

  Widget _information(
    BuildContext context,
    ProductDetail product,
    ErrorPresentation? refreshError,
  ) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: <Widget>[
      if (refreshError != null) ...<Widget>[
        AppInlineError(error: refreshError),
        TextButton(
          onPressed: _controller.refresh,
          child: const Text('Refresh'),
        ),
      ],
      ProductInformation(
        product: product,
        displayedPrice: _controller.displayedPrice,
        isVariantPrice: _controller.isVariantPrice,
        selectedVariantId: _controller.selectedVariantId,
        onVariantChanged: _controller.selectVariant,
        onCategoryTap: () =>
            context.push(AppRoutes.category(product.category.summary.slug)),
      ),
      const SizedBox(height: AppSpacing.space8),
      ProductRequestAction(
        selectedVariant: _controller.selectedVariant,
        onRequest: () => context.push(
          AppRoutes.furnitureRequest(product.id),
          extra: product,
        ),
      ),
    ],
  );
}
