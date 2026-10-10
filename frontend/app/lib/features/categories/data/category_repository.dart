import '../../../core/identifiers/resource_identifier.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_response.dart';
import '../../../core/network/request_cancellation.dart';
import 'category_detail.dart';
import 'category_summary.dart';

class CategoryPage {
  const CategoryPage({required this.categories, required this.pagination});

  final List<CategorySummary> categories;
  final ApiPagination pagination;
}

/// Public category reads (CAT-003 collection, CAT-004 detail).
///
/// Requests are anonymous: no bearer token is attached and no authenticated
/// helper is used, because public catalog reads must work without a session.
abstract interface class CategoryRepository {
  Future<CategoryPage> getCategories({
    required int page,
    RequestCancellation? cancellation,
  });

  /// Resolves [category] by its server-returned slug or opaque ID and returns
  /// the CAT-004 detail representation. An unknown or inactive category is a
  /// `RESOURCE_NOT_FOUND` (404).
  Future<CategoryDetail> getCategory(
    String category, {
    RequestCancellation? cancellation,
  });
}

class ApiCategoryRepository implements CategoryRepository {
  ApiCategoryRepository(this._apiClient);

  static const int _perPage = 20;

  final ApiClient _apiClient;

  @override
  Future<CategoryPage> getCategories({
    required int page,
    RequestCancellation? cancellation,
  }) async {
    final response = await _apiClient.get<List<CategorySummary>>(
      '/categories',
      queryParameters: <String, Object?>{'page': page, 'per_page': _perPage},
      cancellation: cancellation,
      decoder: _decodeCategories,
    );
    if (response == null || response.meta?.pagination == null) {
      throw const FormatException('Category response is missing pagination.');
    }
    return CategoryPage(
      categories: response.data,
      pagination: response.meta!.pagination!,
    );
  }

  @override
  Future<CategoryDetail> getCategory(
    String category, {
    RequestCancellation? cancellation,
  }) async {
    if (!ResourceIdentifier.isValid(category)) {
      throw const FormatException('Invalid category identifier.');
    }
    final response = await _apiClient.get<CategoryDetail>(
      '/categories/${Uri.encodeComponent(category)}',
      cancellation: cancellation,
      decoder: CategoryDetail.fromJson,
    );
    if (response == null) {
      throw const FormatException('Category response is missing data.');
    }
    return response.data;
  }

  static List<CategorySummary> _decodeCategories(Object? value) {
    if (value is! List) {
      throw const FormatException('Category data must be a list.');
    }
    return value.map(CategorySummary.fromJson).toList(growable: false);
  }
}
