// Flutter's Material library exports its own `SearchController` for the
// SearchAnchor; this feature's controller is the one that owns the criteria.
import 'package:flutter/material.dart' hide SearchController;

import '../../catalog/data/catalog_repository.dart';
import '../../categories/data/category_repository.dart';
import 'search_controller.dart';
import 'search_screen.dart';

/// Owns the search controller's lifetime for one visit to `/search`.
///
/// The router registers this rather than the screen itself so the controller is
/// created once and disposed when the route leaves the tree. Creating the
/// controller inside a `builder` would leak a controller and its in-flight
/// requests on every rebuild.
class SearchScreenHost extends StatefulWidget {
  const SearchScreenHost({
    super.key,
    required this.catalogRepository,
    required this.categoryRepository,
  });

  final CatalogRepository catalogRepository;
  final CategoryRepository categoryRepository;

  @override
  State<SearchScreenHost> createState() => _SearchScreenHostState();
}

class _SearchScreenHostState extends State<SearchScreenHost> {
  late final SearchController _controller = SearchController(
    catalogRepository: widget.catalogRepository,
    categoryRepository: widget.categoryRepository,
  );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SearchScreen(controller: _controller);
}
