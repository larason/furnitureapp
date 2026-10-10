import 'package:flutter/foundation.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/api_transport_exception.dart';
import '../../../core/network/request_cancellation.dart';
import '../../../core/presentation/async_view_state.dart';
import '../../../core/presentation/error_presentation_mapper.dart';
import '../../catalog/data/product_summary.dart';
import '../data/product_detail.dart';
import '../data/product_detail_repository.dart';

/// Loads one `CAT-002` product detail and owns transient variant selection.
///
/// Variant selection is presentation state only: nothing is reserved, ordered,
/// or submitted. Changing products creates a new controller, and a selection
/// that no longer belongs to the loaded product is dropped, so one product's
/// option can never appear on another.
class ProductDetailController extends ChangeNotifier {
  ProductDetailController({required this.repository, required this.identifier});

  final ProductDetailRepository repository;

  /// Route identifier: the server slug or opaque ID as supplied by the router.
  final String identifier;

  AsyncViewState<ProductDetail> _state = const AsyncInitial<ProductDetail>();
  String? _selectedVariantId;
  RequestCancellation? _cancellation;
  bool _disposed = false;
  bool _productUnavailable = false;
  int _generation = 0;

  AsyncViewState<ProductDetail> get state => _state;

  /// True when `CAT-002` reported the product as missing or non-public (404).
  bool get productUnavailable => _productUnavailable;

  /// The loaded product, retained while a refresh or retry is in flight.
  ProductDetail? get product => switch (_state) {
    AsyncContent(:final data) => data,
    AsyncRefreshing(:final data) => data,
    AsyncLoading(previousData: final data?) => data,
    AsyncFailure(previousData: final data?) => data,
    _ => null,
  };

  /// Selected variant ID, or null when no option is chosen.
  String? get selectedVariantId => _selectedVariantId;

  /// The selected variant, or null when the product has no variants or nothing
  /// is selected. No variant is selected by default.
  ProductVariantSummary? get selectedVariant {
    final variants = product?.variants;
    final selectedId = _selectedVariantId;
    if (variants == null || selectedId == null) return null;
    for (final variant in variants) {
      if (variant.id == selectedId) return variant;
    }
    return null;
  }

  /// The variant price when an option is chosen, otherwise the product's own
  /// base price. Both are integer minor units straight from the API.
  Money? get displayedPrice => selectedVariant?.price ?? product?.price;

  /// True when the displayed price is a variant price rather than the
  /// product's own base price.
  bool get isVariantPrice => selectedVariant != null;

  Future<void> load() => _load(refresh: false);
  Future<void> refresh() => _load(refresh: true);

  /// Selects one variant, or clears the selection with null. A value that does
  /// not belong to the loaded product is ignored rather than displayed.
  void selectVariant(String? variantId) {
    final variants = product?.variants;
    if (variantId != null &&
        !(variants?.any((variant) => variant.id == variantId) ?? false)) {
      return;
    }
    if (_selectedVariantId == variantId) return;
    _selectedVariantId = variantId;
    _notify();
  }

  Future<void> _load({required bool refresh}) async {
    final generation = ++_generation;
    _cancellation?.cancel();
    final cancellation = _cancellation = RequestCancellation();
    final previous = product;
    _state = refresh && previous != null
        ? AsyncRefreshing(previous)
        : AsyncLoading(previousData: previous);
    _productUnavailable = false;
    _notify();
    try {
      final detail = await repository.getProduct(
        identifier,
        cancellation: cancellation,
      );
      if (!_isCurrent(generation, cancellation)) return;
      _selectedVariantId = null;
      _state = AsyncContent(detail);
    } catch (error) {
      if (!_isCurrent(generation, cancellation) || _isCancellation(error)) {
        return;
      }
      final productUnavailable = error is ApiError && error.statusCode == 404;
      if (productUnavailable) {
        _productUnavailable = true;
      }
      final presentation = ErrorPresentationMapper.from(error);
      if (presentation != null) {
        // An unpublished product is gone, not merely unreachable, so it must
        // not stay on screen behind a recoverable message.
        _state = AsyncFailure(
          presentation,
          previousData: productUnavailable ? null : previous,
        );
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
    super.dispose();
  }
}
