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
import '../data/catalog_repository.dart';
import 'catalog_controller.dart';
import 'product_card.dart';

class CatalogScreen extends StatefulWidget {
  const CatalogScreen({super.key, required this.repository});

  final CatalogRepository repository;

  @override
  State<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends State<CatalogScreen> {
  late final CatalogController _controller = CatalogController(
    widget.repository,
  )..load();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Furniture')),
    body: ListenableBuilder(
      listenable: _controller,
      builder: (context, _) => switch (_controller.state) {
        AsyncInitial() || AsyncLoading(previousData: null) =>
          const AppLoadingView(message: 'Loading furniture'),
        AsyncEmpty() => AppEmptyView(
          title: 'No furniture is available yet',
          description: 'Please check back for made-to-order pieces.',
          actionLabel: 'Refresh',
          onAction: _controller.refresh,
        ),
        AsyncFailure(previousData: null, :final error) => AppErrorView(
          error: error,
          onRecovery: _controller.load,
        ),
        AsyncFailure(previousData: final data?, :final error) => _CatalogList(
          controller: _controller,
          page: data,
          refreshError: error,
        ),
        AsyncContent(:final data) ||
        AsyncRefreshing(:final data) ||
        AsyncLoading(previousData: final data?) ||
        AsyncFailure(
          previousData: final data?,
        ) => _CatalogList(controller: _controller, page: data),
        _ => const SizedBox.shrink(),
      },
    ),
  );
}

class _CatalogList extends StatelessWidget {
  const _CatalogList({
    required this.controller,
    required this.page,
    this.refreshError,
  });
  final CatalogController controller;
  final CatalogPage page;
  final ErrorPresentation? refreshError;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: controller.refresh,
    child: LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= AppSpacing.breakpointTablet
            ? 3
            : constraints.maxWidth >= AppSpacing.breakpointPhone
            ? 2
            : 1;
        return GridView.builder(
          padding: const EdgeInsets.all(AppSpacing.gutterPhone),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            mainAxisSpacing: AppSpacing.space6,
            crossAxisSpacing: AppSpacing.space4,
            childAspectRatio: _childAspectRatio(
              constraints.maxWidth,
              columns,
              MediaQuery.textScalerOf(context).scale(1),
            ),
          ),
          itemCount:
              page.products.length +
              (refreshError != null ? 1 : 0) +
              (page.pagination.hasNext || controller.nextPageError != null
                  ? 1
                  : 0),
          itemBuilder: (context, index) {
            if (refreshError != null && index == 0) {
              return Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.space4),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    AppInlineError(error: refreshError!),
                    TextButton(
                      onPressed: controller.refresh,
                      child: const Text('Refresh'),
                    ),
                  ],
                ),
              );
            }
            final productIndex = index - (refreshError != null ? 1 : 0);
            if (productIndex == page.products.length) {
              if (controller.nextPageError case final error?) {
                return Column(
                  children: <Widget>[
                    AppInlineError(error: error),
                    TextButton(
                      onPressed: controller.loadNextPage,
                      child: const Text('Try again'),
                    ),
                  ],
                );
              }
              return Center(
                child: FilledButton(
                  onPressed: controller.loadNextPage,
                  child: const Text('Load more'),
                ),
              );
            }
            final product = page.products[productIndex];
            return ProductCard(
              product: product,
              onTap: () => context.push(AppRoutes.product(product.id)),
            );
          },
        );
      },
    ),
  );

  double _childAspectRatio(double width, int columns, double textScale) {
    final tileWidth =
        (width -
            (AppSpacing.gutterPhone * 2) -
            (AppSpacing.space4 * (columns - 1))) /
        columns;
    final imageHeight = tileWidth * .75;
    final textHeight = 72 * textScale + AppSpacing.space6;
    return tileWidth / (imageHeight + textHeight);
  }
}
