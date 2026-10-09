import 'package:flutter/material.dart';

import '../../../../theme/app_spacing.dart';
import '../../../catalog/data/catalog_query.dart';
import '../../../categories/data/category_summary.dart';

/// Search field with its submit action.
///
/// The field enforces the contract's 100-character `search` limit so an
/// over-long query never reaches the backend as a 422, and exposes both the
/// keyboard search action and the explicit button the design calls for.
class SearchField extends StatefulWidget {
  const SearchField({
    super.key,
    required this.text,
    required this.onChanged,
    required this.onSubmitted,
    required this.onClear,
  });

  final String text;
  final ValueChanged<String> onChanged;
  final VoidCallback onSubmitted;
  final VoidCallback onClear;

  @override
  State<SearchField> createState() => _SearchFieldState();
}

class _SearchFieldState extends State<SearchField> {
  late final TextEditingController _controller = TextEditingController(
    text: widget.text,
  )..addListener(_handleTextChanged);

  @override
  void didUpdateWidget(SearchField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.text == _controller.text) return;
    _controller.value = TextEditingValue(
      text: widget.text,
      selection: TextSelection.collapsed(offset: widget.text.length),
    );
  }

  /// Keeps the trailing clear control in step with typing.
  ///
  /// A `TextField` rebuilds its own editable state, not this element, so the
  /// suffix control has to be told explicitly when the text changes.
  void _handleTextChanged() => setState(() {});

  @override
  void dispose() {
    _controller
      ..removeListener(_handleTextChanged)
      ..dispose();
    super.dispose();
  }

  bool get _isEmpty => _controller.text.isEmpty;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Expanded(child: _field()),
        const SizedBox(width: AppSpacing.space3),
        Semantics(
          button: true,
          label: 'Search',
          child: IconButton.filled(
            key: const ValueKey<String>('search.submit_button'),
            onPressed: widget.onSubmitted,
            style: IconButton.styleFrom(
              backgroundColor: colors.primary,
              foregroundColor: colors.onPrimary,
            ),
            icon: const Icon(Icons.search),
            tooltip: 'Search',
          ),
        ),
      ],
    );
  }

  Widget _field() => TextField(
    key: const ValueKey<String>('search.text_field'),
    controller: _controller,
    onChanged: widget.onChanged,
    onSubmitted: (_) => widget.onSubmitted(),
    textInputAction: TextInputAction.search,
    autocorrect: false,
    maxLength: CatalogQuery.maxSearchLength,
    decoration: InputDecoration(
      hintText: 'Search furniture',
      labelText: 'Search furniture',
      prefixIcon: const Icon(Icons.search),
      suffixIcon: _isEmpty
          ? null
          : IconButton(
              key: const ValueKey<String>('search.clear_button'),
              onPressed: _clear,
              icon: const Icon(Icons.close),
              tooltip: 'Clear search',
            ),
    ),
  );

  void _clear() {
    _controller.clear();
    widget.onClear();
  }
}

/// Category and sort controls.
///
/// Two labelled dropdowns rather than a taxonomy panel: the public category
/// collection is flat, and the design calls for a compact control. The chosen
/// value is always visible as text, never signalled by colour alone.
class SearchFilters extends StatelessWidget {
  const SearchFilters({
    super.key,
    required this.categories,
    required this.selectedCategorySlug,
    required this.onCategoryChanged,
    required this.sort,
    required this.onSortChanged,
  });

  final List<CategorySummary> categories;
  final String? selectedCategorySlug;
  final ValueChanged<String?> onCategoryChanged;
  final CatalogSort sort;
  final ValueChanged<CatalogSort> onSortChanged;

  static const String allCategoriesLabel = 'All categories';

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final category = _CategoryDropdown(
        categories: categories,
        selectedCategorySlug: selectedCategorySlug,
        onChanged: onCategoryChanged,
      );
      final sorting = _SortDropdown(sort: sort, onChanged: onSortChanged);
      if (constraints.maxWidth < AppSpacing.breakpointPhone) {
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            category,
            const SizedBox(height: AppSpacing.space4),
            sorting,
          ],
        );
      }
      return Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Expanded(child: category),
          const SizedBox(width: AppSpacing.space4),
          Expanded(child: sorting),
        ],
      );
    },
  );
}

class _CategoryDropdown extends StatelessWidget {
  const _CategoryDropdown({
    required this.categories,
    required this.selectedCategorySlug,
    required this.onChanged,
  });

  final List<CategorySummary> categories;
  final String? selectedCategorySlug;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) => KeyedSubtree(
    key: const ValueKey<String>('search.category_filter'),
    child: DropdownButtonFormField<String>(
      key: ValueKey<String>('search.category_filter.$selectedCategorySlug'),
      initialValue: selectedCategorySlug,
      isExpanded: true,
      decoration: const InputDecoration(labelText: 'Category'),
      items: <DropdownMenuItem<String>>[
        DropdownMenuItem<String>(
          value: null,
          child: Text(
            SearchFilters.allCategoriesLabel,
            overflow: TextOverflow.ellipsis,
          ),
        ),
        for (final category in categories)
          DropdownMenuItem<String>(
            value: category.slug,
            child: Text(category.name, overflow: TextOverflow.ellipsis),
          ),
      ],
      onChanged: onChanged,
    ),
  );
}

class _SortDropdown extends StatelessWidget {
  const _SortDropdown({required this.sort, required this.onChanged});

  final CatalogSort sort;
  final ValueChanged<CatalogSort> onChanged;

  @override
  Widget build(BuildContext context) => KeyedSubtree(
    key: const ValueKey<String>('search.sort_filter'),
    child: DropdownButtonFormField<CatalogSort>(
      key: ValueKey<CatalogSort>(sort),
      initialValue: sort,
      isExpanded: true,
      decoration: const InputDecoration(labelText: 'Sort by'),
      items: <DropdownMenuItem<CatalogSort>>[
        for (final option in CatalogSort.values)
          DropdownMenuItem<CatalogSort>(
            value: option,
            child: Text(option.label, overflow: TextOverflow.ellipsis),
          ),
      ],
      onChanged: (value) {
        if (value != null) onChanged(value);
      },
    ),
  );
}

/// Read-only summary of the active criteria with a reset action.
///
/// Active state is stated in words, so it is never communicated by colour alone.
class ActiveCriteriaSummary extends StatelessWidget {
  const ActiveCriteriaSummary({
    super.key,
    required this.criteria,
    required this.categoryName,
    required this.onReset,
  });

  final CatalogQuery criteria;
  final String categoryName;
  final VoidCallback onReset;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.center,
    children: <Widget>[
      Expanded(
        child: Semantics(
          liveRegion: true,
          child: Text(
            _description,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ),
      ),
      TextButton(
        key: const ValueKey<String>('search.reset_button'),
        onPressed: onReset,
        child: const Text('Reset'),
      ),
    ],
  );

  String get _description {
    final search = criteria.normalizedSearch;
    final category = criteria.categorySlug;
    if (search == null && category == null && criteria.sort.isDefault) {
      return 'Showing all made-to-order furniture.';
    }
    final parts = <String>[
      if (search != null) 'matching "$search"',
      if (category != null) 'in $categoryName',
    ];
    final scope = parts.isEmpty
        ? 'all made-to-order furniture'
        : 'furniture ${parts.join(' ')}';
    return 'Showing $scope, sorted by ${criteria.sort.label.toLowerCase()}.';
  }
}

/// Backend-reported result count, never derived from the loaded page length.
class ResultCount extends StatelessWidget {
  const ResultCount({super.key, required this.total});

  final int total;

  @override
  Widget build(BuildContext context) => Semantics(
    liveRegion: true,
    child: Text(
      total == 1 ? '1 piece' : '$total pieces',
      style: Theme.of(context).textTheme.titleSmall,
    ),
  );
}
