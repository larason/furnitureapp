import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:go_router/go_router.dart';

import '../../../core/presentation/app_empty_view.dart';
import '../../../core/presentation/app_error_view.dart';
import '../../../core/presentation/app_inline_error.dart';
import '../../../core/presentation/app_loading_view.dart';
import '../../../core/presentation/async_view_state.dart';
import '../../../core/presentation/error_presentation_mapper.dart';
import '../../../navigation/app_routes.dart';
import '../../../theme/app_media.dart';
import '../../../theme/app_spacing.dart';
import '../data/category_repository.dart';
import 'categories_controller.dart';
import 'widgets/category_tile.dart';

/// Public category index (CAT-003).
///
/// The list shows exactly the categories the API returns — the active
/// storefront categories beneath the structural root. The database taxonomy is
/// deeper, but no subcategory navigation is invented here; the collection is a
/// flat list of room stories.
class CategoriesScreen extends StatefulWidget {
  const CategoriesScreen({super.key, required this.repository});

  final CategoryRepository repository;

  @override
  State<CategoriesScreen> createState() => _CategoriesScreenState();
}

class _CategoriesScreenState extends State<CategoriesScreen> {
  /// Name lines a tile reserves so a long category name never clips, at any
  /// supported text scale.
  static const int _nameLines = 3;

  late final CategoriesController _controller = CategoriesController(
    widget.repository,
  )..load();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Categories')),
    body: ListenableBuilder(
      listenable: _controller,
      builder: (context, _) => switch (_controller.state) {
        AsyncInitial() || AsyncLoading(previousData: null) =>
          const AppLoadingView(message: 'Loading categories'),
        AsyncEmpty() => AppEmptyView(
          title: 'No categories are published yet',
          description: 'Browse made-to-order furniture while we add rooms.',
          actionLabel: 'Refresh',
          onAction: _controller.refresh,
        ),
        AsyncFailure(previousData: null, :final error) => AppErrorView(
          error: error,
          onRecovery: _controller.load,
        ),
        AsyncFailure(previousData: final data?, :final error) => _list(
          data,
          refreshError: error,
        ),
        AsyncContent(:final data) ||
        AsyncRefreshing(:final data) ||
        AsyncLoading(previousData: final data?) => _list(data),
        _ => const SizedBox.shrink(),
      },
    ),
  );

  Widget _list(CategoryPage page, {ErrorPresentation? refreshError}) =>
      RefreshIndicator(
        onRefresh: _controller.refresh,
        child: CustomScrollView(
          slivers: <Widget>[
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.gutterPhone,
                AppSpacing.sectionPhone,
                AppSpacing.gutterPhone,
                AppSpacing.space5,
              ),
              sliver: SliverToBoxAdapter(child: _intro(context)),
            ),
            SliverLayoutBuilder(
              builder: (context, constraints) =>
                  _grid(context, constraints, page, refreshError),
            ),
          ],
        ),
      );

  Widget _intro(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Semantics(
          header: true,
          child: Text('Furniture by room', style: textTheme.headlineLarge),
        ),
        const SizedBox(height: AppSpacing.space3),
        Text(
          'Browse made-to-order furniture for each room in your home.',
          style: textTheme.bodyLarge,
        ),
      ],
    );
  }

  Widget _grid(
    BuildContext context,
    SliverConstraints constraints,
    CategoryPage page,
    ErrorPresentation? refreshError,
  ) {
    final columns = constraints.crossAxisExtent >= AppSpacing.breakpointTablet
        ? 3
        : constraints.crossAxisExtent >= AppSpacing.breakpointPhone
        ? 2
        : 1;
    final gutter = AppSpacing.gutterPhone;
    final tileWidth =
        (constraints.crossAxisExtent - (gutter * 2) - _space(columns)) /
        columns;
    final hasMore =
        page.pagination.hasNext || _controller.nextPageError != null;
    return SliverPadding(
      padding: EdgeInsets.fromLTRB(gutter, 0, gutter, gutter),
      sliver: SliverMainAxisGroup(
        slivers: <Widget>[
          if (refreshError case final error?) _refreshFailure(error),
          SliverGrid(
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: columns,
              mainAxisSpacing: AppSpacing.space6,
              crossAxisSpacing: AppSpacing.space5,
              mainAxisExtent: _tileExtent(context, tileWidth),
            ),
            delegate: SliverChildBuilderDelegate((context, index) {
              final category = page.categories[index];
              return CategoryTile(
                key: ValueKey<String>(category.id),
                category: category,
                onTap: () => context.push(AppRoutes.category(category.slug)),
              );
            }, childCount: page.categories.length),
          ),
          if (hasMore) _pagination(),
        ],
      ),
    );
  }

  double _space(int columns) => AppSpacing.space5 * (columns - 1);

  /// Grid cell height: room photography at the canonical editorial ratio plus
  /// the text area the name needs at the current text scale.
  double _tileExtent(BuildContext context, double tileWidth) {
    final scale = MediaQuery.textScalerOf(context);
    final style = Theme.of(context).textTheme.titleMedium;
    final fontSize = style?.fontSize ?? 16;
    final lineHeight = (style?.height ?? 1.0) * scale.scale(fontSize);
    final imageHeight = tileWidth / AppMedia.editorial;
    final textHeight = (lineHeight * _nameLines) + (AppSpacing.space3 * 2);
    return imageHeight + textHeight;
  }

  Widget _refreshFailure(ErrorPresentation error) => SliverToBoxAdapter(
    child: Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.space5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          AppInlineError(error: error),
          TextButton(
            onPressed: _controller.refresh,
            child: const Text('Refresh'),
          ),
        ],
      ),
    ),
  );

  Widget _pagination() => SliverToBoxAdapter(
    child: Padding(
      padding: const EdgeInsets.only(top: AppSpacing.space6),
      child: switch (_controller.nextPageError) {
        final error? => Column(
          children: <Widget>[
            AppInlineError(error: error),
            TextButton(
              onPressed: _controller.loadNextPage,
              child: const Text('Try again'),
            ),
          ],
        ),
        null => Center(
          child: FilledButton(
            onPressed: _controller.loadNextPage,
            child: const Text('Load more'),
          ),
        ),
      },
    ),
  );
}
