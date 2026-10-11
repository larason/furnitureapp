import 'package:flutter/foundation.dart';

import '../features/categories/data/category_repository.dart';
import '../features/categories/data/category_summary.dart';

enum DrawerCategoriesStatus { idle, loading, content, empty, failure }

class DrawerCategoryController extends ChangeNotifier {
  DrawerCategoryController(this._repository);

  final CategoryRepository? _repository;
  DrawerCategoriesStatus _status = DrawerCategoriesStatus.idle;
  List<CategorySummary> _categories = const <CategorySummary>[];
  Future<void>? _loadFuture;
  bool _disposed = false;

  DrawerCategoriesStatus get status => _status;
  List<CategorySummary> get categories => _categories;

  Future<void> load() {
    if (_status == DrawerCategoriesStatus.loading ||
        _status == DrawerCategoriesStatus.content ||
        _status == DrawerCategoriesStatus.empty) {
      return _loadFuture ?? Future<void>.value();
    }
    final repository = _repository;
    if (repository == null) {
      _setStatus(DrawerCategoriesStatus.empty);
      return Future<void>.value();
    }

    _setStatus(DrawerCategoriesStatus.loading);
    final future = _loadPages(repository);
    _loadFuture = future;
    return future.whenComplete(() {
      if (identical(_loadFuture, future)) _loadFuture = null;
    });
  }

  Future<void> _loadPages(CategoryRepository repository) async {
    try {
      var pageNumber = 1;
      final categories = <CategorySummary>[];
      while (true) {
        final page = await repository.getCategories(page: pageNumber);
        categories.addAll(page.categories);
        if (!page.pagination.hasNext) break;
        pageNumber++;
      }
      if (_disposed) return;
      _categories = List<CategorySummary>.unmodifiable(categories);
      _setStatus(
        categories.isEmpty
            ? DrawerCategoriesStatus.empty
            : DrawerCategoriesStatus.content,
      );
    } catch (_) {
      if (!_disposed) _setStatus(DrawerCategoriesStatus.failure);
    }
  }

  Future<void> retry() {
    if (_status == DrawerCategoriesStatus.loading) {
      return _loadFuture ?? Future<void>.value();
    }
    _status = DrawerCategoriesStatus.idle;
    return load();
  }

  void _setStatus(DrawerCategoriesStatus status) {
    if (_disposed || _status == status) return;
    _status = status;
    notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }
}
