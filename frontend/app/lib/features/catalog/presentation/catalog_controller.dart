import 'package:flutter/foundation.dart';

import '../../../core/network/api_transport_exception.dart';
import '../../../core/network/request_cancellation.dart';
import '../../../core/presentation/async_view_state.dart';
import '../../../core/presentation/error_presentation_mapper.dart';
import '../data/catalog_query.dart';
import '../data/catalog_repository.dart';
import '../data/product_summary.dart';

/// Paginated state for one public `CAT-001` result set.
///
/// Criteria are fixed for the lifetime of the controller: any change of search
/// text, category, or sort builds a **new** controller, which is how pagination
/// is reset and how a previous result set is discarded. Within one result set
/// the server owns ordering, `meta.pagination` stays authoritative, an obsolete
/// request is cancelled and discarded, and a failed page keeps the products
/// already on screen.
class CatalogController extends ChangeNotifier {
  CatalogController(this._repository, {CatalogQuery? query})
    : criteria = (query ?? const CatalogQuery()).forFirstPage();

  final CatalogRepository _repository;

  /// The result set this controller pages through, always starting at page one.
  final CatalogQuery criteria;

  AsyncViewState<CatalogPage> _state = const AsyncInitial<CatalogPage>();
  AsyncViewState<CatalogPage> get state => _state;
  ErrorPresentation? nextPageError;
  RequestCancellation? _cancellation;
  bool _disposed = false;
  bool _loadingNext = false;
  int _requestGeneration = 0;

  Future<void> load() => _loadPage(1, replace: true);
  Future<void> refresh() => _loadPage(1, replace: true, refresh: true);

  Future<void> loadNextPage() async {
    final data = _paginatingData;
    if (data == null || !data.pagination.hasNext || _loadingNext) return;
    final generation = _requestGeneration;
    final cancellation = RequestCancellation();
    _cancellation?.cancel();
    _cancellation = cancellation;
    _loadingNext = true;
    nextPageError = null;
    _notify();
    try {
      final next = await _repository.fetchProducts(
        criteria.withPage(data.pagination.currentPage + 1),
        cancellation: cancellation,
      );
      if (!_isCurrent(generation, cancellation)) return;
      final seen = data.products.map((product) => product.id).toSet();
      final merged = <ProductSummary>[
        ...data.products,
        ...next.products.where((product) => seen.add(product.id)),
      ];
      _state = AsyncContent(
        CatalogPage(products: merged, pagination: next.pagination),
      );
    } catch (error) {
      if (!_isCurrent(generation, cancellation)) return;
      nextPageError = ErrorPresentationMapper.from(error);
    } finally {
      if (_isCurrent(generation, cancellation)) {
        _loadingNext = false;
        _cancellation = null;
        _notify();
      }
    }
  }

  CatalogPage? get _data => switch (_state) {
    AsyncContent(:final data) => data,
    AsyncRefreshing(:final data) => data,
    AsyncLoading(previousData: final data?) => data,
    AsyncFailure(previousData: final data?) => data,
    _ => null,
  };

  /// The page a new pagination request may build on.
  ///
  /// A pending full load or refresh already supersedes the result set, so
  /// pagination yields instead of cancelling the in-flight request and merging
  /// onto data that is about to be replaced.
  CatalogPage? get _paginatingData => switch (_state) {
    AsyncLoading() || AsyncRefreshing() => null,
    _ => _data,
  };

  Future<void> _loadPage(
    int page, {
    required bool replace,
    bool refresh = false,
  }) async {
    final generation = ++_requestGeneration;
    _cancellation?.cancel();
    final cancellation = _cancellation = RequestCancellation();
    final previous = _data;
    _state = refresh && previous != null
        ? AsyncRefreshing(previous)
        : AsyncLoading(previousData: previous);
    _notify();
    _loadingNext = false;
    if (replace && page == CatalogQuery.firstPage) nextPageError = null;
    try {
      final result = await _repository.fetchProducts(
        criteria.withPage(page),
        cancellation: cancellation,
      );
      if (!_isCurrent(generation, cancellation)) return;
      _state = result.products.isEmpty
          ? const AsyncEmpty<CatalogPage>()
          : AsyncContent(result);
    } catch (error) {
      if (!_isCurrent(generation, cancellation) ||
          error is ApiTransportException &&
              error.kind == ApiTransportFailureKind.cancellation) {
        return;
      }
      final presentation = ErrorPresentationMapper.from(error);
      if (presentation != null) {
        _state = AsyncFailure(presentation, previousData: previous);
      }
    } finally {
      if (identical(_cancellation, cancellation)) {
        _cancellation = null;
      }
      _notify();
    }
  }

  bool _isCurrent(int generation, RequestCancellation cancellation) =>
      !_disposed &&
      generation == _requestGeneration &&
      identical(_cancellation, cancellation) &&
      !cancellation.isCancelled;

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _cancellation?.cancel();
    super.dispose();
  }
}
