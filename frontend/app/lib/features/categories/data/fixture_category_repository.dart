import '../../../core/network/api_error.dart';
import '../../../core/network/api_response.dart';
import '../../../core/network/api_transport_exception.dart';
import '../../../core/network/request_cancellation.dart';
import 'category_detail.dart';
import 'category_repository.dart';
import 'category_summary.dart';
import 'fixture_categories.dart';

/// Development fixture implementation of the public category reads.
///
/// Only reachable when `CATALOG_DATA_SOURCE=fixtures` in a local debug build;
/// staging and production always use [ApiCategoryRepository], and no code path
/// falls back to fixtures after an API error.
class FixtureCategoryRepository implements CategoryRepository {
  const FixtureCategoryRepository();

  static const int _perPage = 20;

  @override
  Future<CategoryPage> getCategories({
    required int page,
    RequestCancellation? cancellation,
  }) async {
    _throwIfCancelled(cancellation);
    final total = fixtureCategoryDetails.length;
    final lastPage = total == 0 ? 1 : (total / _perPage).ceil();
    final current = page < 1
        ? 1
        : page > lastPage
        ? lastPage
        : page;
    final start = (current - 1) * _perPage;
    final visible = start < total
        ? fixtureCategoryDetails
              .skip(start)
              .take(_perPage)
              .map((category) => category.summary)
              .toList(growable: false)
        : const <CategorySummary>[];
    return CategoryPage(
      categories: visible,
      pagination: ApiPagination(
        currentPage: current,
        perPage: _perPage,
        total: total,
        lastPage: lastPage,
        hasNext: current < lastPage,
        hasPrevious: current > 1,
      ),
    );
  }

  @override
  Future<CategoryDetail> getCategory(
    String category, {
    RequestCancellation? cancellation,
  }) async {
    _throwIfCancelled(cancellation);
    for (final detail in fixtureCategoryDetails) {
      if (detail.slug == category || detail.id == category) return detail;
    }
    throw ApiError(
      statusCode: 404,
      errors: const <ApiErrorItem>[
        ApiErrorItem(
          code: 'RESOURCE_NOT_FOUND',
          message: 'The requested category was not found.',
        ),
      ],
    );
  }

  static void _throwIfCancelled(RequestCancellation? cancellation) {
    if (cancellation?.isCancelled ?? false) {
      throw const ApiTransportException(
        kind: ApiTransportFailureKind.cancellation,
      );
    }
  }
}
