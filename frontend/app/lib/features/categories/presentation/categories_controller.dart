import 'package:flutter/foundation.dart';

import '../../../core/network/api_transport_exception.dart';
import '../../../core/network/request_cancellation.dart';
import '../../../core/presentation/async_view_state.dart';
import '../../../core/presentation/error_presentation_mapper.dart';
import '../data/category_repository.dart';
import '../data/category_summary.dart';

/// Paginated state for the public category collection (CAT-003).
///
/// Follows the Phase 17.1 catalog conventions: server ordering and
/// `meta.pagination` stay authoritative, a duplicate page request is ignored
/// while one is in flight, an obsolete request is cancelled and discarded, and
/// a failed page keeps the categories already on screen.
class CategoriesController extends ChangeNotifier {
  CategoriesController(this._repository);

  final CategoryRepository _repository;
  AsyncViewState<CategoryPage> _state = const AsyncInitial<CategoryPage>();
  RequestCancellation? _cancellation;
  bool _disposed = false;
  bool _loadingNext = false;
  int _requestGeneration = 0;

  AsyncViewState<CategoryPage> get state => _state;
  ErrorPresentation? nextPageError;

  Future<void> load() => _load(1, refresh: false);
  Future<void> refresh() => _load(1, refresh: true);

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
      final next = await _repository.getCategories(
        page: data.pagination.currentPage + 1,
        cancellation: cancellation,
      );
      if (!_isCurrent(generation, cancellation)) return;
      final seen = data.categories.map((category) => category.id).toSet();
      final merged = <CategorySummary>[
        ...data.categories,
        ...next.categories.where((category) => seen.add(category.id)),
      ];
      _state = AsyncContent(
        CategoryPage(categories: merged, pagination: next.pagination),
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

  CategoryPage? get _data => switch (_state) {
    AsyncContent(:final data) => data,
    AsyncRefreshing(:final data) => data,
    AsyncLoading(previousData: final data?) => data,
    AsyncFailure(previousData: final data?) => data,
    _ => null,
  };

  /// The page a new collection request may build on.
  ///
  /// A pending full load or refresh already supersedes the collection, so
  /// pagination yields instead of cancelling the in-flight request and merging
  /// onto stale data.
  CategoryPage? get _paginatingData => switch (_state) {
    AsyncLoading() || AsyncRefreshing() => null,
    _ => _data,
  };

  Future<void> _load(int page, {required bool refresh}) async {
    final generation = ++_requestGeneration;
    _cancellation?.cancel();
    final cancellation = _cancellation = RequestCancellation();
    final previous = _data;
    _state = refresh && previous != null
        ? AsyncRefreshing(previous)
        : AsyncLoading(previousData: previous);
    _notify();
    _loadingNext = false;
    nextPageError = null;
    try {
      final result = await _repository.getCategories(
        page: page,
        cancellation: cancellation,
      );
      if (!_isCurrent(generation, cancellation)) return;
      _state = result.categories.isEmpty
          ? const AsyncEmpty<CategoryPage>()
          : AsyncContent(result);
    } catch (error) {
      if (!_isCurrent(generation, cancellation) || _isCancellation(error)) {
        return;
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

  bool _isCancellation(Object error) =>
      error is ApiTransportException &&
      error.kind == ApiTransportFailureKind.cancellation;

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
