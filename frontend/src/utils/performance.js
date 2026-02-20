/**
 * ============================================================================
 * FRONTEND PERFORMANCE UTILITIES
 * ============================================================================
 * Optimizations for React components to handle 1000+ concurrent users
 * ============================================================================
 */

import { useCallback, useEffect, useRef, useState, useMemo } from 'react';

/**
 * ============================================================================
 * DEBOUNCE AND THROTTLE HOOKS
 * ============================================================================
 */

/**
 * Debounce hook for optimizing frequent updates
 * Perfect for search inputs, API calls
 */
export function useDebounce(value, delay = 500) {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedValue(value);
    }, delay);

    return () => {
      clearTimeout(handler);
    };
  }, [value, delay]);

  return debouncedValue;
}

/**
 * Throttle hook for limiting execution rate
 * Perfect for scroll handlers, resize handlers
 */
export function useThrottle(value, limit = 500) {
  const [throttledValue, setThrottledValue] = useState(value);
  const lastRan = useRef(Date.now());

  useEffect(() => {
    const handler = setTimeout(() => {
      if (Date.now() - lastRan.current >= limit) {
        setThrottledValue(value);
        lastRan.current = Date.now();
      }
    }, limit - (Date.now() - lastRan.current));

    return () => {
      clearTimeout(handler);
    };
  }, [value, limit]);

  return throttledValue;
}

/**
 * ============================================================================
 * VIRTUAL SCROLLING HOOK
 * ============================================================================
 */

/**
 * Virtual scrolling for large lists (10,000+ items)
 * Only renders visible items for better performance
 */
export function useVirtualScroll({
  itemCount,
  itemHeight,
  containerHeight,
  overscan = 3
}) {
  const [scrollTop, setScrollTop] = useState(0);

  const visibleStart = Math.floor(scrollTop / itemHeight);
  const visibleEnd = Math.ceil((scrollTop + containerHeight) / itemHeight);

  const start = Math.max(0, visibleStart - overscan);
  const end = Math.min(itemCount, visibleEnd + overscan);

  const offsetY = start * itemHeight;
  const totalHeight = itemCount * itemHeight;

  return {
    virtualItems: { start, end },
    offsetY,
    totalHeight,
    onScroll: (e) => setScrollTop(e.target.scrollTop)
  };
}

/**
 * ============================================================================
 * INTERSECTION OBSERVER HOOK (Lazy Loading)
 * ============================================================================
 */

/**
 * Lazy load components when they enter viewport
 * Perfect for images, heavy components
 */
export function useIntersectionObserver(options = {}) {
  const [isIntersecting, setIsIntersecting] = useState(false);
  const [hasIntersected, setHasIntersected] = useState(false);
  const targetRef = useRef(null);

  useEffect(() => {
    const target = targetRef.current;
    if (!target) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        setIsIntersecting(entry.isIntersecting);
        if (entry.isIntersecting && !hasIntersected) {
          setHasIntersected(true);
        }
      },
      {
        threshold: 0.1,
        rootMargin: '50px',
        ...options
      }
    );

    observer.observe(target);

    return () => {
      observer.disconnect();
    };
  }, [hasIntersected, options]);

  return { targetRef, isIntersecting, hasIntersected };
}

/**
 * ============================================================================
 * API REQUEST OPTIMIZATION
 * ============================================================================
 */

/**
 * Request deduplication - prevents duplicate API calls
 */
class RequestDeduplicator {
  constructor() {
    this.pendingRequests = new Map();
  }

  async deduplicate(key, requestFn) {
    // If request is already pending, return the same promise
    if (this.pendingRequests.has(key)) {
      return this.pendingRequests.get(key);
    }

    // Create new request
    const promise = requestFn()
      .finally(() => {
        // Clean up after request completes
        this.pendingRequests.delete(key);
      });

    this.pendingRequests.set(key, promise);
    return promise;
  }

  clear() {
    this.pendingRequests.clear();
  }
}

export const apiDeduplicator = new RequestDeduplicator();

/**
 * Cache API responses in memory
 */
class APICache {
  constructor(maxSize = 100, ttl = 5 * 60 * 1000) {
    this.cache = new Map();
    this.maxSize = maxSize;
    this.ttl = ttl;
  }

  get(key) {
    const item = this.cache.get(key);
    
    if (!item) return null;
    
    // Check if expired
    if (Date.now() - item.timestamp > this.ttl) {
      this.cache.delete(key);
      return null;
    }
    
    return item.data;
  }

  set(key, data) {
    // Implement LRU eviction
    if (this.cache.size >= this.maxSize) {
      const firstKey = this.cache.keys().next().value;
      this.cache.delete(firstKey);
    }
    
    this.cache.set(key, {
      data,
      timestamp: Date.now()
    });
  }

  clear() {
    this.cache.clear();
  }

  invalidate(pattern) {
    const keys = Array.from(this.cache.keys());
    keys.forEach(key => {
      if (key.includes(pattern)) {
        this.cache.delete(key);
      }
    });
  }
}

export const apiCache = new APICache();

/**
 * Optimized fetch hook with caching and deduplication
 */
export function useOptimizedFetch(url, options = {}) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const abortControllerRef = useRef(null);

  const { 
    cache = true, 
    deduplicate = true,
    dependencies = []
  } = options;

  const fetchData = useCallback(async () => {
    // Cancel previous request
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }

    // Check cache first
    if (cache) {
      const cached = apiCache.get(url);
      if (cached) {
        setData(cached);
        return cached;
      }
    }

    setLoading(true);
    setError(null);

    abortControllerRef.current = new AbortController();

    try {
      const fetchFn = async () => {
        const response = await fetch(url, {
          ...options,
          signal: abortControllerRef.current.signal
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
      };

      const result = deduplicate
        ? await apiDeduplicator.deduplicate(url, fetchFn)
        : await fetchFn();

      setData(result);
      
      // Store in cache
      if (cache) {
        apiCache.set(url, result);
      }

      return result;
    } catch (err) {
      if (err.name !== 'AbortError') {
        setError(err.message);
        console.error('Fetch error:', err);
      }
      throw err;
    } finally {
      setLoading(false);
    }
  }, [url, cache, deduplicate, ...dependencies]);

  useEffect(() => {
    fetchData();

    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, [fetchData]);

  return { data, loading, error, refetch: fetchData };
}

/**
 * ============================================================================
 * BATCH API REQUESTS
 * ============================================================================
 */

/**
 * Batch multiple API requests to reduce network overhead
 */
export class APIBatcher {
  constructor(batchDelay = 50) {
    this.queue = [];
    this.batchDelay = batchDelay;
    this.timeout = null;
  }

  add(request) {
    return new Promise((resolve, reject) => {
      this.queue.push({ request, resolve, reject });

      if (this.timeout) {
        clearTimeout(this.timeout);
      }

      this.timeout = setTimeout(() => {
        this.flush();
      }, this.batchDelay);
    });
  }

  async flush() {
    if (this.queue.length === 0) return;

    const batch = [...this.queue];
    this.queue = [];

    try {
      // Send batched request
      const requests = batch.map(item => item.request);
      const response = await fetch('/api/batch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ requests })
      });

      const results = await response.json();

      // Resolve individual promises
      batch.forEach((item, index) => {
        item.resolve(results[index]);
      });
    } catch (error) {
      // Reject all promises
      batch.forEach(item => {
        item.reject(error);
      });
    }
  }
}

export const apiBatcher = new APIBatcher();

/**
 * ============================================================================
 * PAGINATION HELPER
 * ============================================================================
 */

/**
 * Optimized pagination hook
 */
export function usePagination({
  totalItems,
  itemsPerPage = 50,
  initialPage = 1
}) {
  const [currentPage, setCurrentPage] = useState(initialPage);

  const totalPages = Math.ceil(totalItems / itemsPerPage);

  const goToPage = useCallback((page) => {
    const validPage = Math.max(1, Math.min(page, totalPages));
    setCurrentPage(validPage);
  }, [totalPages]);

  const nextPage = useCallback(() => {
    goToPage(currentPage + 1);
  }, [currentPage, goToPage]);

  const prevPage = useCallback(() => {
    goToPage(currentPage - 1);
  }, [currentPage, goToPage]);

  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = Math.min(startIndex + itemsPerPage, totalItems);

  return {
    currentPage,
    totalPages,
    startIndex,
    endIndex,
    goToPage,
    nextPage,
    prevPage,
    hasNextPage: currentPage < totalPages,
    hasPrevPage: currentPage > 1
  };
}

/**
 * ============================================================================
 * PERFORMANCE MONITORING
 * ============================================================================
 */

/**
 * Monitor component render performance
 */
export function useRenderPerformance(componentName) {
  const renderCount = useRef(0);
  const startTime = useRef(performance.now());

  useEffect(() => {
    renderCount.current += 1;
    const renderTime = performance.now() - startTime.current;

    if (renderTime > 16) { // Longer than one frame (60fps)
      console.warn(
        `[Performance] ${componentName} render #${renderCount.current} took ${renderTime.toFixed(2)}ms`
      );
    }

    startTime.current = performance.now();
  });

  return renderCount.current;
}

/**
 * Measure function execution time
 */
export function measurePerformance(fn, label) {
  return async (...args) => {
    const start = performance.now();
    const result = await fn(...args);
    const duration = performance.now() - start;
    
    console.log(`[Performance] ${label}: ${duration.toFixed(2)}ms`);
    
    return result;
  };
}

/**
 * ============================================================================
 * WEB WORKERS FOR HEAVY COMPUTATIONS
 * ============================================================================
 */

/**
 * Offload heavy computations to Web Worker
 */
export function useWebWorker(workerFunction) {
  const workerRef = useRef(null);

  useEffect(() => {
    // Create worker from function
    const blob = new Blob(
      [`self.onmessage = ${workerFunction.toString()}`],
      { type: 'application/javascript' }
    );
    const workerUrl = URL.createObjectURL(blob);
    workerRef.current = new Worker(workerUrl);

    return () => {
      if (workerRef.current) {
        workerRef.current.terminate();
        URL.revokeObjectURL(workerUrl);
      }
    };
  }, [workerFunction]);

  const runWorker = useCallback((data) => {
    return new Promise((resolve, reject) => {
      if (!workerRef.current) {
        reject(new Error('Worker not initialized'));
        return;
      }

      workerRef.current.onmessage = (e) => resolve(e.data);
      workerRef.current.onerror = (e) => reject(e);
      workerRef.current.postMessage(data);
    });
  }, []);

  return runWorker;
}

/**
 * ============================================================================
 * OPTIMIZED TABLE COMPONENT HELPERS
 * ============================================================================
 */

/**
 * Memoized table row component
 */
export function createMemoizedRow(RowComponent) {
  return React.memo(RowComponent, (prevProps, nextProps) => {
    // Custom comparison - only re-render if data changed
    return prevProps.data === nextProps.data &&
           prevProps.selected === nextProps.selected;
  });
}

/**
 * ============================================================================
 * EXPORTS
 * ============================================================================
 */

export default {
  useDebounce,
  useThrottle,
  useVirtualScroll,
  useIntersectionObserver,
  useOptimizedFetch,
  usePagination,
  useRenderPerformance,
  useWebWorker,
  apiCache,
  apiDeduplicator,
  apiBatcher,
  measurePerformance,
  createMemoizedRow
};
