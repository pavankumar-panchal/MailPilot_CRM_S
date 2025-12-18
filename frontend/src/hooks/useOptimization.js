import { useState, useEffect, useRef, useCallback } from "react";

/**
 * Custom hook for debouncing values
 * @param {*} value - The value to debounce
 * @param {number} delay - Delay in milliseconds
 * @returns Debounced value
 */
export const useDebounce = (value, delay = 500) => {
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
};

/**
 * Custom hook for caching API responses
 * @param {number} ttl - Time to live in milliseconds (default: 5 minutes)
 * @returns Cache utilities
 */
export const useCache = (ttl = 5 * 60 * 1000) => {
  const cacheRef = useRef(new Map());

  const get = useCallback((key) => {
    const cached = cacheRef.current.get(key);
    if (!cached) return null;

    const isExpired = Date.now() - cached.timestamp > ttl;
    if (isExpired) {
      cacheRef.current.delete(key);
      return null;
    }

    return cached.data;
  }, [ttl]);

  const set = useCallback((key, data) => {
    cacheRef.current.set(key, {
      data,
      timestamp: Date.now(),
    });
  }, []);

  const clear = useCallback((key) => {
    if (key) {
      cacheRef.current.delete(key);
    } else {
      cacheRef.current.clear();
    }
  }, []);

  const has = useCallback((key) => {
    const cached = cacheRef.current.get(key);
    if (!cached) return false;
    
    const isExpired = Date.now() - cached.timestamp > ttl;
    if (isExpired) {
      cacheRef.current.delete(key);
      return false;
    }
    
    return true;
  }, [ttl]);

  return { get, set, clear, has };
};

/**
 * Custom hook for fetching data with caching and loading states
 * @param {Function} fetchFn - The fetch function
 * @param {Array} dependencies - Dependencies array
 * @param {Object} options - Options (cache, cacheKey, initialData)
 * @returns {Object} - { data, loading, error, refetch }
 */
export const useFetch = (fetchFn, dependencies = [], options = {}) => {
  const {
    cache = true,
    cacheKey = null,
    initialData = null,
    ttl = 5 * 60 * 1000,
  } = options;

  const [data, setData] = useState(initialData);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const { get, set } = useCache(ttl);
  const mountedRef = useRef(true);

  const fetchData = useCallback(async () => {
    try {
      // Check cache first if enabled
      if (cache && cacheKey) {
        const cached = get(cacheKey);
        if (cached) {
          setData(cached);
          setLoading(false);
          return;
        }
      }

      setLoading(true);
      setError(null);

      const result = await fetchFn();

      if (mountedRef.current) {
        setData(result);
        
        // Cache the result if enabled
        if (cache && cacheKey) {
          set(cacheKey, result);
        }
      }
    } catch (err) {
      if (mountedRef.current) {
        setError(err);
        console.error("Fetch error:", err);
      }
    } finally {
      if (mountedRef.current) {
        setLoading(false);
      }
    }
  }, [fetchFn, cache, cacheKey, get, set]);

  useEffect(() => {
    fetchData();
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fetchData, ...dependencies]);

  useEffect(() => {
    return () => {
      mountedRef.current = false;
    };
  }, []);

  return { data, loading, error, refetch: fetchData };
};

/**
 * Custom hook for infinite scroll / lazy loading
 * @param {Function} fetchMore - Function to fetch more data
 * @param {boolean} hasMore - Whether there's more data to load
 * @returns {Object} - { ref, isIntersecting }
 */
export const useInfiniteScroll = (fetchMore, hasMore = true) => {
  const [isIntersecting, setIsIntersecting] = useState(false);
  const targetRef = useRef(null);

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        setIsIntersecting(entry.isIntersecting);
        if (entry.isIntersecting && hasMore) {
          fetchMore();
        }
      },
      {
        root: null,
        rootMargin: "100px",
        threshold: 0.1,
      }
    );

    const currentTarget = targetRef.current;
    if (currentTarget) {
      observer.observe(currentTarget);
    }

    return () => {
      if (currentTarget) {
        observer.unobserve(currentTarget);
      }
    };
  }, [fetchMore, hasMore]);

  return { ref: targetRef, isIntersecting };
};

/**
 * Custom hook for throttling function calls
 * @param {Function} callback - Function to throttle
 * @param {number} delay - Delay in milliseconds
 * @returns Throttled function
 */
export const useThrottle = (callback, delay = 500) => {
  const lastRan = useRef(Date.now());

  return useCallback(
    (...args) => {
      if (Date.now() - lastRan.current >= delay) {
        callback(...args);
        lastRan.current = Date.now();
      }
    },
    [callback, delay]
  );
};

/**
 * Custom hook for local storage with state sync
 * @param {string} key - Storage key
 * @param {*} initialValue - Initial value
 * @returns [value, setValue, removeValue]
 */
export const useLocalStorage = (key, initialValue) => {
  const [storedValue, setStoredValue] = useState(() => {
    try {
      const item = window.localStorage.getItem(key);
      return item ? JSON.parse(item) : initialValue;
    } catch (error) {
      console.error(`Error loading ${key} from localStorage:`, error);
      return initialValue;
    }
  });

  const setValue = useCallback(
    (value) => {
      try {
        const valueToStore = value instanceof Function ? value(storedValue) : value;
        setStoredValue(valueToStore);
        window.localStorage.setItem(key, JSON.stringify(valueToStore));
      } catch (error) {
        console.error(`Error saving ${key} to localStorage:`, error);
      }
    },
    [key, storedValue]
  );

  const removeValue = useCallback(() => {
    try {
      window.localStorage.removeItem(key);
      setStoredValue(initialValue);
    } catch (error) {
      console.error(`Error removing ${key} from localStorage:`, error);
    }
  }, [key, initialValue]);

  return [storedValue, setValue, removeValue];
};

export default {
  useDebounce,
  useCache,
  useFetch,
  useInfiniteScroll,
  useThrottle,
  useLocalStorage,
};
