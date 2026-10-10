import '../../../core/identifiers/resource_identifier.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/request_cancellation.dart';
import 'product_detail.dart';

/// Public product detail read (`CAT-002`).
///
/// The request is anonymous: no bearer token is attached, no session is
/// required, and the shared `ApiClient` stays in public auth mode, so product
/// detail keeps working while Clerk is restoring or temporarily unavailable.
/// An unknown, unpublished, or otherwise non-public product is a
/// `RESOURCE_NOT_FOUND` (404).
abstract interface class ProductDetailRepository {
  /// Resolves [product] by its server-returned slug or opaque ID.
  Future<ProductDetail> getProduct(
    String product, {
    RequestCancellation? cancellation,
  });
}

class ApiProductDetailRepository implements ProductDetailRepository {
  ApiProductDetailRepository(this._apiClient);

  final ApiClient _apiClient;

  @override
  Future<ProductDetail> getProduct(
    String product, {
    RequestCancellation? cancellation,
  }) async {
    if (!ResourceIdentifier.isValid(product)) {
      throw const FormatException('Invalid product identifier.');
    }
    final response = await _apiClient.get<ProductDetail>(
      '/products/${Uri.encodeComponent(product)}',
      cancellation: cancellation,
      decoder: ProductDetail.fromJson,
    );
    if (response == null) {
      throw const FormatException('Product response is missing data.');
    }
    return response.data;
  }
}
