class ApiResponse<T> {
  const ApiResponse({required this.data, this.meta});

  final T data;
  final ApiMeta? meta;
}

class ApiMeta {
  const ApiMeta({this.pagination, this.unreadCount, this.requestId});

  final ApiPagination? pagination;
  final int? unreadCount;
  final String? requestId;
}

class ApiPagination {
  const ApiPagination({
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
    required this.hasNext,
    required this.hasPrevious,
  });

  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;
  final bool hasNext;
  final bool hasPrevious;
}
