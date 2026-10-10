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
import '../../catalog/data/catalog_repository.dart';
import '../../catalog/presentation/catalog_controller.dart';
import '../../catalog/presentation/product_grid.dart';
import '../data/category_detail.dart';
import '../data/category_repository.dart';
import 'category_detail_controller.dart';
import 'widgets/category_header.dart';

/// Public category landing page (CAT-004 + CAT-001).
///
/// Identity and products load independently: a product failure keeps the valid
/// category on screen with a recoverable message, a 404 becomes an unavailable
/// category state instead of an empty listing, and products always filter by
/// the canonical slug the API returned through `GET /products?category=`.
class CategoryDetailScreen extends StatefulWidget {
  const CategoryDetailScreen({
    super.key,
    required this.identifier,
    required this.categoryRepository,
    required this.catalogRepository,
  });

  final String identifier;
  final CategoryRepository categoryRepository;
  final CatalogRepository catalogRepository;

  @override
  State<CategoryDetailScreen> createState() => _CategoryDetailScreenState();
}

class _CategoryDetailScreenState extends State<CategoryDetailScreen> {
  late CategoryDetailController _controller = _createController();

  CategoryDetailController _createController() => CategoryDetailController(
    categoryRepository: widget.categoryRepository,
    catalogRepository: widget.catalogRepository,
    identifier: widget.identifier,
  )..load();

  @override
  void didUpdateWidget(CategoryDetailScreen oldWidget) {
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
      final detail = _detailOf(state);
      return Scaffold(
        appBar: AppBar(
          title: Text(
            detail?.name ?? 'Category',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
        body: switch (state) {
          AsyncInitial() || AsyncLoading(previousData: null) =>
            const AppLoadingView(message: 'Loading category'),
          AsyncFailure(previousData: null, :final error) => _failure(
            context,
            error,
          ),
          AsyncContent(:final data) ||
          AsyncRefreshing(:final data) ||
          AsyncLoading(previousData: final data?) => _content(data, null),
          AsyncFailure(previousData: final data?, :final error) => _content(
            data,
            error,
          ),
          _ => const SizedBox.shrink(),
        },
      );
    },
  );

  CategoryDetail? _detailOf(AsyncViewState<CategoryDetail> state) =>
      switch (state) {
        AsyncContent(:final data) => data,
        AsyncRefreshing(:final data) => data,
        AsyncLoading(previousData: final data?) => data,
        AsyncFailure(previousData: final data?) => data,
        _ => null,
      };

  Widget _failure(BuildContext context, ErrorPresentation error) {
    if (!_controller.categoryUnavailable) {
      return AppErrorView(error: error, onRecovery: _controller.load);
    }
    return AppEmptyView(
      title: 'This category is unavailable',
      description: 'It may have been moved or is no longer published.',
      actionLabel: 'Browse categories',
      onAction: () => context.go(AppRoutes.categories),
    );
  }

  Widget _content(CategoryDetail detail, ErrorPresentation? detailError) {
    final products = _controller.products;
    if (products == null) {
      return const AppLoadingView(message: 'Loading furniture');
    }
    return ListenableBuilder(
      listenable: products,
      builder: (context, _) => RefreshIndicator(
        onRefresh: _controller.refresh,
        child: CustomScrollView(
          slivers: <Widget>[
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.gutterPhone,
                AppSpacing.space6,
                AppSpacing.gutterPhone,
                AppSpacing.space5,
              ),
              sliver: SliverToBoxAdapter(
                child: _heading(context, detail, detailError),
              ),
            ),
            ..._productSlivers(products),
          ],
        ),
      ),
    );
  }

  Widget _heading(
    BuildContext context,
    CategoryDetail detail,
    ErrorPresentation? detailError,
  ) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      CategoryHeader(category: detail),
      if (detailError != null) ...<Widget>[
        const SizedBox(height: AppSpacing.space5),
        AppInlineError(error: detailError),
        TextButton(
          onPressed: _controller.refresh,
          child: const Text('Refresh'),
        ),
      ],
      const SizedBox(height: AppSpacing.space6),
      Semantics(
        header: true,
        child: Text(
          'Made to order',
          style: Theme.of(context).textTheme.headlineLarge,
        ),
      ),
    ],
  );

  List<Widget> _productSlivers(
    CatalogController products,
  ) => switch (products.state) {
    AsyncInitial() || AsyncLoading(previousData: null) => const <Widget>[
      SliverFillRemaining(child: AppLoadingView(message: 'Loading furniture')),
    ],
    AsyncEmpty() => <Widget>[
      SliverFillRemaining(
        child: AppEmptyView(
          title: 'No made-to-order furniture here yet',
          description:
              'This room has no published pieces right now. Check back soon.',
          actionLabel: 'Refresh',
          onAction: products.refresh,
        ),
      ),
    ],
    AsyncFailure(previousData: null, :final error) => <Widget>[
      SliverFillRemaining(
        child: AppErrorView(error: error, onRecovery: products.load),
      ),
    ],
    AsyncFailure(previousData: final data?, :final error) => <Widget>[
      ProductGridSliver(controller: products, page: data, refreshError: error),
    ],
    AsyncContent(:final data) ||
    AsyncRefreshing(:final data) ||
    AsyncLoading(
      previousData: final data?,
    ) => <Widget>[ProductGridSliver(controller: products, page: data)],
    _ => const <Widget>[],
  };
}
