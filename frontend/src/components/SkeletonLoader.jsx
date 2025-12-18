import React from "react";

// Table Row Skeleton
export const TableRowSkeleton = ({ columns = 7 }) => (
  <tr className="animate-pulse">
    {Array.from({ length: columns }).map((_, idx) => (
      <td key={idx} className="px-6 py-4">
        <div className="h-4 bg-gray-200 rounded w-3/4"></div>
      </td>
    ))}
  </tr>
);

// Table Skeleton
export const TableSkeleton = ({ rows = 10, columns = 7 }) => (
  <>
    {Array.from({ length: rows }).map((_, idx) => (
      <TableRowSkeleton key={idx} columns={columns} />
    ))}
  </>
);

// Card Skeleton
export const CardSkeleton = () => (
  <div className="bg-white rounded-xl shadow-md p-6 animate-pulse">
    <div className="flex justify-between items-start mb-4">
      <div className="flex-1">
        <div className="h-6 bg-gray-200 rounded w-3/4 mb-3"></div>
        <div className="h-4 bg-gray-200 rounded w-1/2"></div>
      </div>
      <div className="h-8 w-8 bg-gray-200 rounded-full"></div>
    </div>
    <div className="grid grid-cols-3 gap-4 mb-4">
      <div>
        <div className="h-3 bg-gray-200 rounded w-1/2 mb-2"></div>
        <div className="h-5 bg-gray-200 rounded w-3/4"></div>
      </div>
      <div>
        <div className="h-3 bg-gray-200 rounded w-1/2 mb-2"></div>
        <div className="h-5 bg-gray-200 rounded w-3/4"></div>
      </div>
      <div>
        <div className="h-3 bg-gray-200 rounded w-1/2 mb-2"></div>
        <div className="h-5 bg-gray-200 rounded w-3/4"></div>
      </div>
    </div>
    <div className="flex gap-2">
      <div className="h-9 bg-gray-200 rounded w-24"></div>
      <div className="h-9 bg-gray-200 rounded w-24"></div>
      <div className="h-9 bg-gray-200 rounded w-24"></div>
    </div>
  </div>
);

// List of Cards Skeleton
export const CardListSkeleton = ({ count = 6 }) => (
  <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
    {Array.from({ length: count }).map((_, idx) => (
      <CardSkeleton key={idx} />
    ))}
  </div>
);

// Stats Card Skeleton
export const StatsCardSkeleton = () => (
  <div className="bg-white rounded-lg shadow p-4 animate-pulse">
    <div className="flex items-center justify-between mb-2">
      <div className="h-4 bg-gray-200 rounded w-1/2"></div>
      <div className="h-8 w-8 bg-gray-200 rounded-full"></div>
    </div>
    <div className="h-8 bg-gray-200 rounded w-3/4 mb-2"></div>
    <div className="h-3 bg-gray-200 rounded w-1/2"></div>
  </div>
);

// List Item Skeleton
export const ListItemSkeleton = () => (
  <div className="flex items-center p-4 bg-white border-b border-gray-200 animate-pulse">
    <div className="h-10 w-10 bg-gray-200 rounded-full mr-4"></div>
    <div className="flex-1">
      <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
      <div className="h-3 bg-gray-200 rounded w-1/2"></div>
    </div>
    <div className="h-8 bg-gray-200 rounded w-16"></div>
  </div>
);

// Shimmer effect skeleton (more modern)
export const ShimmerSkeleton = ({ className = "" }) => (
  <div className={`relative overflow-hidden bg-gray-200 ${className}`}>
    <div className="absolute inset-0 -translate-x-full animate-[shimmer_2s_infinite] bg-gradient-to-r from-transparent via-white to-transparent"></div>
  </div>
);

// Email row virtualized skeleton
export const EmailRowSkeleton = ({ style }) => (
  <div
    style={style}
    className="flex items-center border-b border-gray-100 px-6 py-4 animate-pulse"
  >
    <div className="w-16 mr-4">
      <div className="h-4 bg-gray-200 rounded"></div>
    </div>
    <div className="flex-1 mr-4">
      <div className="h-4 bg-gray-200 rounded w-3/4"></div>
    </div>
    <div className="w-32 mr-4">
      <div className="h-4 bg-gray-200 rounded"></div>
    </div>
    <div className="w-32 mr-4">
      <div className="h-4 bg-gray-200 rounded"></div>
    </div>
    <div className="w-24 mr-4">
      <div className="h-6 bg-gray-200 rounded-full"></div>
    </div>
    <div className="w-24 mr-4">
      <div className="h-6 bg-gray-200 rounded-full"></div>
    </div>
    <div className="w-40">
      <div className="h-4 bg-gray-200 rounded"></div>
    </div>
  </div>
);

export default {
  TableRowSkeleton,
  TableSkeleton,
  CardSkeleton,
  CardListSkeleton,
  StatsCardSkeleton,
  ListItemSkeleton,
  ShimmerSkeleton,
  EmailRowSkeleton,
};
