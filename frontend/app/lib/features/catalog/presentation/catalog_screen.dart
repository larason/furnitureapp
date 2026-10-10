import 'package:flutter/material.dart';

import '../../../core/presentation/app_empty_view.dart';
import '../../../core/presentation/app_error_view.dart';
import '../../../core/presentation/app_loading_view.dart';
import '../../../core/presentation/async_view_state.dart';
import '../../../core/presentation/error_presentation_mapper.dart';
import '../data/catalog_repository.dart';
import 'catalog_controller.dart';
import 'product_grid.dart';

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
        AsyncFailure(previousData: final data?, :final error) => _catalog(
          data,
          refreshError: error,
        ),
        AsyncContent(:final data) ||
        AsyncRefreshing(:final data) ||
        AsyncLoading(previousData: final data?) => _catalog(data),
        _ => const SizedBox.shrink(),
      },
    ),
  );

  Widget _catalog(CatalogPage page, {ErrorPresentation? refreshError}) =>
      RefreshIndicator(
        onRefresh: _controller.refresh,
        child: CustomScrollView(
          slivers: <Widget>[
            ProductGridSliver(
              controller: _controller,
              page: page,
              refreshError: refreshError,
            ),
          ],
        ),
      );
}
