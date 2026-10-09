import 'package:flutter/foundation.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/api_transport_exception.dart';
import '../../../core/network/request_cancellation.dart';
import '../../../core/presentation/async_view_state.dart';
import '../../../core/presentation/error_presentation_mapper.dart';
import '../../catalog/data/catalog_repository.dart';
import '../../catalog/presentation/catalog_controller.dart';
import '../data/category_detail.dart';
import '../data/category_repository.dart';

/// Loads CAT-004 identity and the category's CAT-001 products as two
/// independent operations.
///
/// A product failure never discards valid category information, and a missing
/// category is reported as unavailable instead of being shown as an empty
/// listing. Products always query the canonical slug returned by CAT-004 —
/// never an identifier inferred locally — through `GET /products?category=`.
class CategoryDetailController extends ChangeNotifier {
  CategoryDetailController({
    required this.categoryRepository,
    required this.catalogRepository,
    required this.identifier,
  });

  final CategoryRepository categoryRepository;
  final CatalogRepository catalogRepository;

  /// Route identifier: the server slug or opaque ID as supplied by the router.
  final String identifier;

  AsyncViewState<CategoryDetail> _state = const AsyncInitial<CategoryDetail>();
  CatalogController? _products;
  RequestCancellation? _cancellation;
  bool _disposed = false;
  bool _categoryUnavailable = false;
  int _generation = 0;

  AsyncViewState<CategoryDetail> get state => _state;

  /// Product listing scoped to the canonical slug. Null until the category
  /// detail has resolved.
  CatalogController? get products => _products;

  /// True when CAT-004 reported the category as missing or inactive (404).
  bool get categoryUnavailable => _categoryUnavailable;

  Future<void> load() => _load(refresh: false);
  Future<void> refresh() => _load(refresh: true);

  Future<void> _load({required bool refresh}) async {
    final generation = ++_generation;
    _cancellation?.cancel();
    final cancellation = _cancellation = RequestCancellation();
    final previous = _data;
    _state = refresh && previous != null
        ? AsyncRefreshing(previous)
        : AsyncLoading(previousData: previous);
    _categoryUnavailable = false;
    _notify();
    try {
      final detail = await categoryRepository.getCategory(
        identifier,
        cancellation: cancellation,
      );
      if (!_isCurrent(generation, cancellation)) return;
      _attachProducts(detail.slug, refresh: refresh);
      _state = AsyncContent(detail);
    } catch (error) {
      if (!_isCurrent(generation, cancellation) || _isCancellation(error)) {
        return;
      }
      if (error is ApiError && error.statusCode == 404) {
        _categoryUnavailable = true;
      }
      final presentation = ErrorPresentationMapper.from(error);
      if (presentation != null) {
        _state = AsyncFailure(presentation, previousData: previous);
      }
    } finally {
      if (identical(_cancellation, cancellation)) _cancellation = null;
      _notify();
    }
  }

  void _attachProducts(String slug, {required bool refresh}) {
    final existing = _products;
    if (existing != null && existing.categorySlug == slug) {
      if (refresh) existing.refresh();
      return;
    }
    existing?.dispose();
    _products = CatalogController(catalogRepository, categorySlug: slug)
      ..load();
  }

  CategoryDetail? get _data => switch (_state) {
    AsyncContent(:final data) => data,
    AsyncRefreshing(:final data) => data,
    AsyncLoading(previousData: final data?) => data,
    AsyncFailure(previousData: final data?) => data,
    _ => null,
  };

  bool _isCancellation(Object error) =>
      error is ApiTransportException &&
      error.kind == ApiTransportFailureKind.cancellation;

  bool _isCurrent(int generation, RequestCancellation cancellation) =>
      !_disposed &&
      generation == _generation &&
      identical(_cancellation, cancellation) &&
      !cancellation.isCancelled;

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _cancellation?.cancel();
    _products?.dispose();
    super.dispose();
  }
}
