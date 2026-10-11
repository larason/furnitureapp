import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/network/api_response.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/categories/data/category_detail.dart';
import 'package:sl_furnitures/features/categories/data/category_repository.dart';
import 'package:sl_furnitures/features/categories/data/category_summary.dart';
import 'package:sl_furnitures/navigation/drawer_category_controller.dart';

void main() {
  group('DrawerCategoryController', () {
    test('loads every paginated category once and caches the result', () async {
      final repository = _CategoryRepository(<CategoryPage>[
        _page(const <CategorySummary>[
          CategorySummary(
            id: 'category-1',
            name: 'Living Room',
            slug: 'living',
          ),
        ], hasNext: true),
        _page(const <CategorySummary>[
          CategorySummary(id: 'category-2', name: 'Bedroom', slug: 'bedroom'),
        ]),
      ]);
      final controller = DrawerCategoryController(repository);
      addTearDown(controller.dispose);

      await controller.load();
      await controller.load();

      expect(controller.status, DrawerCategoriesStatus.content);
      expect(controller.categories.map((category) => category.slug), <String>[
        'living',
        'bedroom',
      ]);
      expect(repository.requestedPages, <int>[1, 2]);
    });

    test('allows a retry after loading fails', () async {
      final repository = _CategoryRepository(<CategoryPage>[
        _page(const <CategorySummary>[]),
      ], failuresRemaining: 1);
      final controller = DrawerCategoryController(repository);
      addTearDown(controller.dispose);

      await controller.load();
      expect(controller.status, DrawerCategoriesStatus.failure);

      await controller.retry();
      expect(controller.status, DrawerCategoriesStatus.empty);
      expect(repository.requestedPages, <int>[1, 1]);
    });

    test(
      'retry returns the existing load while categories are loading',
      () async {
        final loadCompleter = Completer<void>();
        final repository = _CategoryRepository(<CategoryPage>[
          _page(const <CategorySummary>[]),
        ], loadCompleter: loadCompleter);
        final controller = DrawerCategoryController(repository);
        addTearDown(controller.dispose);

        final load = controller.load();
        final retry = controller.retry();

        expect(repository.requestedPages, <int>[1]);
        loadCompleter.complete();
        await Future.wait(<Future<void>>[load, retry]);
        expect(controller.status, DrawerCategoriesStatus.empty);
      },
    );
  });
}

CategoryPage _page(List<CategorySummary> categories, {bool hasNext = false}) =>
    CategoryPage(
      categories: categories,
      pagination: ApiPagination(
        currentPage: 1,
        perPage: 20,
        total: categories.length,
        lastPage: hasNext ? 2 : 1,
        hasNext: hasNext,
        hasPrevious: false,
      ),
    );

class _CategoryRepository implements CategoryRepository {
  _CategoryRepository(
    this.pages, {
    this.failuresRemaining = 0,
    this.loadCompleter,
  });

  final List<CategoryPage> pages;
  final List<int> requestedPages = <int>[];
  int failuresRemaining;
  final Completer<void>? loadCompleter;

  @override
  Future<CategoryDetail> getCategory(
    String category, {
    RequestCancellation? cancellation,
  }) => throw UnimplementedError();

  @override
  Future<CategoryPage> getCategories({
    required int page,
    RequestCancellation? cancellation,
  }) async {
    requestedPages.add(page);
    if (failuresRemaining > 0) {
      failuresRemaining--;
      throw Exception('Category service unavailable');
    }
    if (loadCompleter case final completer?) await completer.future;
    return pages[page - 1];
  }
}
