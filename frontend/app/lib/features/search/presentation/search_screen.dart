// Flutter's Material library exports its own `SearchController` for the
// SearchAnchor; this feature's controller is the one that owns the criteria.
import 'package:flutter/material.dart' hide SearchController;

import '../../../core/presentation/app_empty_view.dart';
import '../../../core/presentation/app_error_view.dart';
import '../../../core/presentation/app_loading_view.dart';
import '../../../core/presentation/async_view_state.dart';
import '../../../core/presentation/error_presentation_mapper.dart';
import '../../../theme/app_spacing.dart';
import '../../catalog/data/catalog_repository.dart';
import '../../catalog/presentation/product_grid.dart';
import '../../categories/data/category_summary.dart';
import 'search_controller.dart';
import 'widgets/search_controls.dart';

/// Public furniture search and filtering.
///
/// The screen follows the design reference: a heading, the search field and its
/// action, category and sort controls, a plain-language summary of what is
/// active, and the same photographic grid the catalog uses. Nothing decorative
/// and no popularity claims — the contract exposes no ranking data.
///
/// Before any criteria are entered the screen shows the made-to-order catalog,
/// which is what the customer would see by tapping "Furniture" instead. That
/// keeps the screen useful rather than presenting an empty prompt.
class SearchScreen extends StatefulWidget {
  const SearchScreen({super.key, required this.controller});

  final SearchController controller;

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Search')),
      body: ListenableBuilder(
        listenable: widget.controller,
        builder: (context, _) => RefreshIndicator(
          onRefresh: widget.controller.results.refresh,
          child: CustomScrollView(
            slivers: <Widget>[
              SliverToBoxAdapter(child: _controls(context, theme)),
              SliverToBoxAdapter(child: _criteria(context)),
              ..._resultSlivers(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _controls(BuildContext context, ThemeData theme) => Padding(
    padding: const EdgeInsets.fromLTRB(
      AppSpacing.gutterPhone,
      AppSpacing.space6,
      AppSpacing.gutterPhone,
      AppSpacing.space5,
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Semantics(
          header: true,
          child: Text(
            'Find your furniture',
            style: theme.textTheme.headlineLarge,
          ),
        ),
        const SizedBox(height: AppSpacing.space2),
        Text(
          'Discover made-to-order furniture for your space.',
          style: theme.textTheme.bodyMedium,
        ),
        const SizedBox(height: AppSpacing.space5),
        SearchField(
          text: widget.controller.searchText,
          onChanged: widget.controller.onSearchTextChanged,
          onSubmitted: widget.controller.submitSearch,
          onClear: widget.controller.clearSearch,
        ),
        const SizedBox(height: AppSpacing.space4),
        SearchFilters(
          categories: _categories(widget.controller),
          selectedCategorySlug: widget.controller.criteria.categorySlug,
          onCategoryChanged: widget.controller.selectCategory,
          sort: widget.controller.criteria.sort,
          onSortChanged: widget.controller.selectSort,
        ),
      ],
    ),
  );

  /// Categories that failed to load are simply omitted: search text, sorting,
  /// and browsing all furniture stay usable.
  List<CategorySummary> _categories(SearchController controller) =>
      switch (controller.categories.state) {
        AsyncContent(:final data) => data.categories,
        AsyncRefreshing(:final data) => data.categories,
        _ => const <CategorySummary>[],
      };

  Widget _criteria(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(
      AppSpacing.gutterPhone,
      0,
      AppSpacing.gutterPhone,
      AppSpacing.space4,
    ),
    child: ActiveCriteriaSummary(
      criteria: widget.controller.criteria,
      categoryName: _selectedCategoryName(),
      onReset: widget.controller.reset,
    ),
  );

  String _selectedCategoryName() {
    final slug = widget.controller.criteria.categorySlug;
    if (slug == null) return '';
    return _categories(widget.controller)
            .where((category) => category.slug == slug)
            .map((category) => category.name)
            .firstOrNull ??
        slug;
  }

  List<Widget> _resultSlivers() => switch (widget.controller.results.state) {
    AsyncInitial() || AsyncLoading(previousData: null) => const <Widget>[
      SliverFillRemaining(child: AppLoadingView(message: 'Loading furniture')),
    ],
    AsyncFailure(previousData: null, :final error) => <Widget>[
      SliverFillRemaining(
        child: AppErrorView(
          error: error,
          onRecovery: widget.controller.results.load,
        ),
      ),
    ],
    AsyncEmpty() => <Widget>[
      SliverFillRemaining(
        child: AppEmptyView(
          title: 'No furniture matches your search',
          description:
              'Try another keyword, adjust your filters, or reset to see all '
              'made-to-order pieces.',
          actionLabel: 'Reset filters',
          onAction: widget.controller.reset,
        ),
      ),
    ],
    AsyncContent(:final data) ||
    AsyncRefreshing(:final data) ||
    AsyncLoading(previousData: final data?) => _grid(data),
    AsyncFailure(previousData: final data?, :final error) => _grid(
      data,
      refreshError: error,
    ),
    _ => const <Widget>[],
  };

  List<Widget> _grid(CatalogPage page, {ErrorPresentation? refreshError}) =>
      <Widget>[
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.gutterPhone,
              0,
              AppSpacing.gutterPhone,
              AppSpacing.space4,
            ),
            child: ResultCount(total: page.pagination.total),
          ),
        ),
        ProductGridSliver(
          controller: widget.controller.results,
          page: page,
          refreshError: refreshError,
        ),
      ];
}
