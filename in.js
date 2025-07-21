(function () {
  const u = document.createElement("link").relList;
  if (u && u.supports && u.supports("modulepreload")) return;
  for (const f of document.querySelectorAll('link[rel="modulepreload"]')) c(f);
  new MutationObserver((f) => {
    for (const m of f)
      if (m.type === "childList")
        for (const x of m.addedNodes)
          x.tagName === "LINK" && x.rel === "modulepreload" && c(x);
  }).observe(document, { childList: !0, subtree: !0 });
  function o(f) {
    const m = {};
    return (
      f.integrity && (m.integrity = f.integrity),
      f.referrerPolicy && (m.referrerPolicy = f.referrerPolicy),
      f.crossOrigin === "use-credentials"
        ? (m.credentials = "include")
        : f.crossOrigin === "anonymous"
        ? (m.credentials = "omit")
        : (m.credentials = "same-origin"),
      m
    );
  }
  function c(f) {
    if (f.ep) return;
    f.ep = !0;
    const m = o(f);
    fetch(f.href, m);
  }
})();
var Iu = { exports: {} },
  Yn = {};
/**
 * @license React
 * react-jsx-runtime.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */ var Sm;
function ox() {
  if (Sm) return Yn;
  Sm = 1;
  var s = Symbol.for("react.transitional.element"),
    u = Symbol.for("react.fragment");
  function o(c, f, m) {
    var x = null;
    if (
      (m !== void 0 && (x = "" + m),
      f.key !== void 0 && (x = "" + f.key),
      "key" in f)
    ) {
      m = {};
      for (var b in f) b !== "key" && (m[b] = f[b]);
    } else m = f;
    return (
      (f = m.ref),
      { $$typeof: s, type: c, key: x, ref: f !== void 0 ? f : null, props: m }
    );
  }
  return (Yn.Fragment = u), (Yn.jsx = o), (Yn.jsxs = o), Yn;
}
var Nm;
function fx() {
  return Nm || ((Nm = 1), (Iu.exports = ox())), Iu.exports;
}
var r = fx(),
  ec = { exports: {} },
  Vn = {},
  tc = { exports: {} },
  ac = {};
/**
 * @license React
 * scheduler.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */ var wm;
function dx() {
  return (
    wm ||
      ((wm = 1),
      (function (s) {
        function u(S, H) {
          var $ = S.length;
          S.push(H);
          e: for (; 0 < $; ) {
            var ee = ($ - 1) >>> 1,
              g = S[ee];
            if (0 < f(g, H)) (S[ee] = H), (S[$] = g), ($ = ee);
            else break e;
          }
        }
        function o(S) {
          return S.length === 0 ? null : S[0];
        }
        function c(S) {
          if (S.length === 0) return null;
          var H = S[0],
            $ = S.pop();
          if ($ !== H) {
            S[0] = $;
            e: for (var ee = 0, g = S.length, M = g >>> 1; ee < M; ) {
              var J = 2 * (ee + 1) - 1,
                K = S[J],
                P = J + 1,
                le = S[P];
              if (0 > f(K, $))
                P < g && 0 > f(le, K)
                  ? ((S[ee] = le), (S[P] = $), (ee = P))
                  : ((S[ee] = K), (S[J] = $), (ee = J));
              else if (P < g && 0 > f(le, $))
                (S[ee] = le), (S[P] = $), (ee = P);
              else break e;
            }
          }
          return H;
        }
        function f(S, H) {
          var $ = S.sortIndex - H.sortIndex;
          return $ !== 0 ? $ : S.id - H.id;
        }
        if (
          ((s.unstable_now = void 0),
          typeof performance == "object" &&
            typeof performance.now == "function")
        ) {
          var m = performance;
          s.unstable_now = function () {
            return m.now();
          };
        } else {
          var x = Date,
            b = x.now();
          s.unstable_now = function () {
            return x.now() - b;
          };
        }
        var y = [],
          p = [],
          v = 1,
          R = null,
          N = 3,
          L = !1,
          w = !1,
          C = !1,
          D = !1,
          Y = typeof setTimeout == "function" ? setTimeout : null,
          Z = typeof clearTimeout == "function" ? clearTimeout : null,
          U = typeof setImmediate < "u" ? setImmediate : null;
        function q(S) {
          for (var H = o(p); H !== null; ) {
            if (H.callback === null) c(p);
            else if (H.startTime <= S)
              c(p), (H.sortIndex = H.expirationTime), u(y, H);
            else break;
            H = o(p);
          }
        }
        function Q(S) {
          if (((C = !1), q(S), !w))
            if (o(y) !== null) (w = !0), W || ((W = !0), Me());
            else {
              var H = o(p);
              H !== null && pe(Q, H.startTime - S);
            }
        }
        var W = !1,
          re = -1,
          ue = 5,
          ce = -1;
        function je() {
          return D ? !0 : !(s.unstable_now() - ce < ue);
        }
        function Ue() {
          if (((D = !1), W)) {
            var S = s.unstable_now();
            ce = S;
            var H = !0;
            try {
              e: {
                (w = !1), C && ((C = !1), Z(re), (re = -1)), (L = !0);
                var $ = N;
                try {
                  t: {
                    for (
                      q(S), R = o(y);
                      R !== null && !(R.expirationTime > S && je());

                    ) {
                      var ee = R.callback;
                      if (typeof ee == "function") {
                        (R.callback = null), (N = R.priorityLevel);
                        var g = ee(R.expirationTime <= S);
                        if (((S = s.unstable_now()), typeof g == "function")) {
                          (R.callback = g), q(S), (H = !0);
                          break t;
                        }
                        R === o(y) && c(y), q(S);
                      } else c(y);
                      R = o(y);
                    }
                    if (R !== null) H = !0;
                    else {
                      var M = o(p);
                      M !== null && pe(Q, M.startTime - S), (H = !1);
                    }
                  }
                  break e;
                } finally {
                  (R = null), (N = $), (L = !1);
                }
                H = void 0;
              }
            } finally {
              H ? Me() : (W = !1);
            }
          }
        }
        var Me;
        if (typeof U == "function")
          Me = function () {
            U(Ue);
          };
        else if (typeof MessageChannel < "u") {
          var F = new MessageChannel(),
            oe = F.port2;
          (F.port1.onmessage = Ue),
            (Me = function () {
              oe.postMessage(null);
            });
        } else
          Me = function () {
            Y(Ue, 0);
          };
        function pe(S, H) {
          re = Y(function () {
            S(s.unstable_now());
          }, H);
        }
        (s.unstable_IdlePriority = 5),
          (s.unstable_ImmediatePriority = 1),
          (s.unstable_LowPriority = 4),
          (s.unstable_NormalPriority = 3),
          (s.unstable_Profiling = null),
          (s.unstable_UserBlockingPriority = 2),
          (s.unstable_cancelCallback = function (S) {
            S.callback = null;
          }),
          (s.unstable_forceFrameRate = function (S) {
            0 > S || 125 < S
              ? console.error(
                  "forceFrameRate takes a positive int between 0 and 125, forcing frame rates higher than 125 fps is not supported"
                )
              : (ue = 0 < S ? Math.floor(1e3 / S) : 5);
          }),
          (s.unstable_getCurrentPriorityLevel = function () {
            return N;
          }),
          (s.unstable_next = function (S) {
            switch (N) {
              case 1:
              case 2:
              case 3:
                var H = 3;
                break;
              default:
                H = N;
            }
            var $ = N;
            N = H;
            try {
              return S();
            } finally {
              N = $;
            }
          }),
          (s.unstable_requestPaint = function () {
            D = !0;
          }),
          (s.unstable_runWithPriority = function (S, H) {
            switch (S) {
              case 1:
              case 2:
              case 3:
              case 4:
              case 5:
                break;
              default:
                S = 3;
            }
            var $ = N;
            N = S;
            try {
              return H();
            } finally {
              N = $;
            }
          }),
          (s.unstable_scheduleCallback = function (S, H, $) {
            var ee = s.unstable_now();
            switch (
              (typeof $ == "object" && $ !== null
                ? (($ = $.delay),
                  ($ = typeof $ == "number" && 0 < $ ? ee + $ : ee))
                : ($ = ee),
              S)
            ) {
              case 1:
                var g = -1;
                break;
              case 2:
                g = 250;
                break;
              case 5:
                g = 1073741823;
                break;
              case 4:
                g = 1e4;
                break;
              default:
                g = 5e3;
            }
            return (
              (g = $ + g),
              (S = {
                id: v++,
                callback: H,
                priorityLevel: S,
                startTime: $,
                expirationTime: g,
                sortIndex: -1,
              }),
              $ > ee
                ? ((S.sortIndex = $),
                  u(p, S),
                  o(y) === null &&
                    S === o(p) &&
                    (C ? (Z(re), (re = -1)) : (C = !0), pe(Q, $ - ee)))
                : ((S.sortIndex = g),
                  u(y, S),
                  w || L || ((w = !0), W || ((W = !0), Me()))),
              S
            );
          }),
          (s.unstable_shouldYield = je),
          (s.unstable_wrapCallback = function (S) {
            var H = N;
            return function () {
              var $ = N;
              N = H;
              try {
                return S.apply(this, arguments);
              } finally {
                N = $;
              }
            };
          });
      })(ac)),
    ac
  );
}
var Em;
function mx() {
  return Em || ((Em = 1), (tc.exports = dx())), tc.exports;
}
var lc = { exports: {} },
  de = {};
/**
 * @license React
 * react.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */ var Tm;
function hx() {
  if (Tm) return de;
  Tm = 1;
  var s = Symbol.for("react.transitional.element"),
    u = Symbol.for("react.portal"),
    o = Symbol.for("react.fragment"),
    c = Symbol.for("react.strict_mode"),
    f = Symbol.for("react.profiler"),
    m = Symbol.for("react.consumer"),
    x = Symbol.for("react.context"),
    b = Symbol.for("react.forward_ref"),
    y = Symbol.for("react.suspense"),
    p = Symbol.for("react.memo"),
    v = Symbol.for("react.lazy"),
    R = Symbol.iterator;
  function N(g) {
    return g === null || typeof g != "object"
      ? null
      : ((g = (R && g[R]) || g["@@iterator"]),
        typeof g == "function" ? g : null);
  }
  var L = {
      isMounted: function () {
        return !1;
      },
      enqueueForceUpdate: function () {},
      enqueueReplaceState: function () {},
      enqueueSetState: function () {},
    },
    w = Object.assign,
    C = {};
  function D(g, M, J) {
    (this.props = g),
      (this.context = M),
      (this.refs = C),
      (this.updater = J || L);
  }
  (D.prototype.isReactComponent = {}),
    (D.prototype.setState = function (g, M) {
      if (typeof g != "object" && typeof g != "function" && g != null)
        throw Error(
          "takes an object of state variables to update or a function which returns an object of state variables."
        );
      this.updater.enqueueSetState(this, g, M, "setState");
    }),
    (D.prototype.forceUpdate = function (g) {
      this.updater.enqueueForceUpdate(this, g, "forceUpdate");
    });
  function Y() {}
  Y.prototype = D.prototype;
  function Z(g, M, J) {
    (this.props = g),
      (this.context = M),
      (this.refs = C),
      (this.updater = J || L);
  }
  var U = (Z.prototype = new Y());
  (U.constructor = Z), w(U, D.prototype), (U.isPureReactComponent = !0);
  var q = Array.isArray,
    Q = { H: null, A: null, T: null, S: null, V: null },
    W = Object.prototype.hasOwnProperty;
  function re(g, M, J, K, P, le) {
    return (
      (J = le.ref),
      { $$typeof: s, type: g, key: M, ref: J !== void 0 ? J : null, props: le }
    );
  }
  function ue(g, M) {
    return re(g.type, M, void 0, void 0, void 0, g.props);
  }
  function ce(g) {
    return typeof g == "object" && g !== null && g.$$typeof === s;
  }
  function je(g) {
    var M = { "=": "=0", ":": "=2" };
    return (
      "$" +
      g.replace(/[=:]/g, function (J) {
        return M[J];
      })
    );
  }
  var Ue = /\/+/g;
  function Me(g, M) {
    return typeof g == "object" && g !== null && g.key != null
      ? je("" + g.key)
      : M.toString(36);
  }
  function F() {}
  function oe(g) {
    switch (g.status) {
      case "fulfilled":
        return g.value;
      case "rejected":
        throw g.reason;
      default:
        switch (
          (typeof g.status == "string"
            ? g.then(F, F)
            : ((g.status = "pending"),
              g.then(
                function (M) {
                  g.status === "pending" &&
                    ((g.status = "fulfilled"), (g.value = M));
                },
                function (M) {
                  g.status === "pending" &&
                    ((g.status = "rejected"), (g.reason = M));
                }
              )),
          g.status)
        ) {
          case "fulfilled":
            return g.value;
          case "rejected":
            throw g.reason;
        }
    }
    throw g;
  }
  function pe(g, M, J, K, P) {
    var le = typeof g;
    (le === "undefined" || le === "boolean") && (g = null);
    var te = !1;
    if (g === null) te = !0;
    else
      switch (le) {
        case "bigint":
        case "string":
        case "number":
          te = !0;
          break;
        case "object":
          switch (g.$$typeof) {
            case s:
            case u:
              te = !0;
              break;
            case v:
              return (te = g._init), pe(te(g._payload), M, J, K, P);
          }
      }
    if (te)
      return (
        (P = P(g)),
        (te = K === "" ? "." + Me(g, 0) : K),
        q(P)
          ? ((J = ""),
            te != null && (J = te.replace(Ue, "$&/") + "/"),
            pe(P, M, J, "", function (ia) {
              return ia;
            }))
          : P != null &&
            (ce(P) &&
              (P = ue(
                P,
                J +
                  (P.key == null || (g && g.key === P.key)
                    ? ""
                    : ("" + P.key).replace(Ue, "$&/") + "/") +
                  te
              )),
            M.push(P)),
        1
      );
    te = 0;
    var tt = K === "" ? "." : K + ":";
    if (q(g))
      for (var Re = 0; Re < g.length; Re++)
        (K = g[Re]), (le = tt + Me(K, Re)), (te += pe(K, M, J, le, P));
    else if (((Re = N(g)), typeof Re == "function"))
      for (g = Re.call(g), Re = 0; !(K = g.next()).done; )
        (K = K.value), (le = tt + Me(K, Re++)), (te += pe(K, M, J, le, P));
    else if (le === "object") {
      if (typeof g.then == "function") return pe(oe(g), M, J, K, P);
      throw (
        ((M = String(g)),
        Error(
          "Objects are not valid as a React child (found: " +
            (M === "[object Object]"
              ? "object with keys {" + Object.keys(g).join(", ") + "}"
              : M) +
            "). If you meant to render a collection of children, use an array instead."
        ))
      );
    }
    return te;
  }
  function S(g, M, J) {
    if (g == null) return g;
    var K = [],
      P = 0;
    return (
      pe(g, K, "", "", function (le) {
        return M.call(J, le, P++);
      }),
      K
    );
  }
  function H(g) {
    if (g._status === -1) {
      var M = g._result;
      (M = M()),
        M.then(
          function (J) {
            (g._status === 0 || g._status === -1) &&
              ((g._status = 1), (g._result = J));
          },
          function (J) {
            (g._status === 0 || g._status === -1) &&
              ((g._status = 2), (g._result = J));
          }
        ),
        g._status === -1 && ((g._status = 0), (g._result = M));
    }
    if (g._status === 1) return g._result.default;
    throw g._result;
  }
  var $ =
    typeof reportError == "function"
      ? reportError
      : function (g) {
          if (
            typeof window == "object" &&
            typeof window.ErrorEvent == "function"
          ) {
            var M = new window.ErrorEvent("error", {
              bubbles: !0,
              cancelable: !0,
              message:
                typeof g == "object" &&
                g !== null &&
                typeof g.message == "string"
                  ? String(g.message)
                  : String(g),
              error: g,
            });
            if (!window.dispatchEvent(M)) return;
          } else if (
            typeof process == "object" &&
            typeof process.emit == "function"
          ) {
            process.emit("uncaughtException", g);
            return;
          }
          console.error(g);
        };
  function ee() {}
  return (
    (de.Children = {
      map: S,
      forEach: function (g, M, J) {
        S(
          g,
          function () {
            M.apply(this, arguments);
          },
          J
        );
      },
      count: function (g) {
        var M = 0;
        return (
          S(g, function () {
            M++;
          }),
          M
        );
      },
      toArray: function (g) {
        return (
          S(g, function (M) {
            return M;
          }) || []
        );
      },
      only: function (g) {
        if (!ce(g))
          throw Error(
            "React.Children.only expected to receive a single React element child."
          );
        return g;
      },
    }),
    (de.Component = D),
    (de.Fragment = o),
    (de.Profiler = f),
    (de.PureComponent = Z),
    (de.StrictMode = c),
    (de.Suspense = y),
    (de.__CLIENT_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE = Q),
    (de.__COMPILER_RUNTIME = {
      __proto__: null,
      c: function (g) {
        return Q.H.useMemoCache(g);
      },
    }),
    (de.cache = function (g) {
      return function () {
        return g.apply(null, arguments);
      };
    }),
    (de.cloneElement = function (g, M, J) {
      if (g == null)
        throw Error(
          "The argument must be a React element, but you passed " + g + "."
        );
      var K = w({}, g.props),
        P = g.key,
        le = void 0;
      if (M != null)
        for (te in (M.ref !== void 0 && (le = void 0),
        M.key !== void 0 && (P = "" + M.key),
        M))
          !W.call(M, te) ||
            te === "key" ||
            te === "__self" ||
            te === "__source" ||
            (te === "ref" && M.ref === void 0) ||
            (K[te] = M[te]);
      var te = arguments.length - 2;
      if (te === 1) K.children = J;
      else if (1 < te) {
        for (var tt = Array(te), Re = 0; Re < te; Re++)
          tt[Re] = arguments[Re + 2];
        K.children = tt;
      }
      return re(g.type, P, void 0, void 0, le, K);
    }),
    (de.createContext = function (g) {
      return (
        (g = {
          $$typeof: x,
          _currentValue: g,
          _currentValue2: g,
          _threadCount: 0,
          Provider: null,
          Consumer: null,
        }),
        (g.Provider = g),
        (g.Consumer = { $$typeof: m, _context: g }),
        g
      );
    }),
    (de.createElement = function (g, M, J) {
      var K,
        P = {},
        le = null;
      if (M != null)
        for (K in (M.key !== void 0 && (le = "" + M.key), M))
          W.call(M, K) &&
            K !== "key" &&
            K !== "__self" &&
            K !== "__source" &&
            (P[K] = M[K]);
      var te = arguments.length - 2;
      if (te === 1) P.children = J;
      else if (1 < te) {
        for (var tt = Array(te), Re = 0; Re < te; Re++)
          tt[Re] = arguments[Re + 2];
        P.children = tt;
      }
      if (g && g.defaultProps)
        for (K in ((te = g.defaultProps), te))
          P[K] === void 0 && (P[K] = te[K]);
      return re(g, le, void 0, void 0, null, P);
    }),
    (de.createRef = function () {
      return { current: null };
    }),
    (de.forwardRef = function (g) {
      return { $$typeof: b, render: g };
    }),
    (de.isValidElement = ce),
    (de.lazy = function (g) {
      return { $$typeof: v, _payload: { _status: -1, _result: g }, _init: H };
    }),
    (de.memo = function (g, M) {
      return { $$typeof: p, type: g, compare: M === void 0 ? null : M };
    }),
    (de.startTransition = function (g) {
      var M = Q.T,
        J = {};
      Q.T = J;
      try {
        var K = g(),
          P = Q.S;
        P !== null && P(J, K),
          typeof K == "object" &&
            K !== null &&
            typeof K.then == "function" &&
            K.then(ee, $);
      } catch (le) {
        $(le);
      } finally {
        Q.T = M;
      }
    }),
    (de.unstable_useCacheRefresh = function () {
      return Q.H.useCacheRefresh();
    }),
    (de.use = function (g) {
      return Q.H.use(g);
    }),
    (de.useActionState = function (g, M, J) {
      return Q.H.useActionState(g, M, J);
    }),
    (de.useCallback = function (g, M) {
      return Q.H.useCallback(g, M);
    }),
    (de.useContext = function (g) {
      return Q.H.useContext(g);
    }),
    (de.useDebugValue = function () {}),
    (de.useDeferredValue = function (g, M) {
      return Q.H.useDeferredValue(g, M);
    }),
    (de.useEffect = function (g, M, J) {
      var K = Q.H;
      if (typeof J == "function")
        throw Error(
          "useEffect CRUD overload is not enabled in this build of React."
        );
      return K.useEffect(g, M);
    }),
    (de.useId = function () {
      return Q.H.useId();
    }),
    (de.useImperativeHandle = function (g, M, J) {
      return Q.H.useImperativeHandle(g, M, J);
    }),
    (de.useInsertionEffect = function (g, M) {
      return Q.H.useInsertionEffect(g, M);
    }),
    (de.useLayoutEffect = function (g, M) {
      return Q.H.useLayoutEffect(g, M);
    }),
    (de.useMemo = function (g, M) {
      return Q.H.useMemo(g, M);
    }),
    (de.useOptimistic = function (g, M) {
      return Q.H.useOptimistic(g, M);
    }),
    (de.useReducer = function (g, M, J) {
      return Q.H.useReducer(g, M, J);
    }),
    (de.useRef = function (g) {
      return Q.H.useRef(g);
    }),
    (de.useState = function (g) {
      return Q.H.useState(g);
    }),
    (de.useSyncExternalStore = function (g, M, J) {
      return Q.H.useSyncExternalStore(g, M, J);
    }),
    (de.useTransition = function () {
      return Q.H.useTransition();
    }),
    (de.version = "19.1.0"),
    de
  );
}
var _m;
function bc() {
  return _m || ((_m = 1), (lc.exports = hx())), lc.exports;
}
var nc = { exports: {} },
  Ie = {};
/**
 * @license React
 * react-dom.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */ var Am;
function px() {
  if (Am) return Ie;
  Am = 1;
  var s = bc();
  function u(y) {
    var p = "https://react.dev/errors/" + y;
    if (1 < arguments.length) {
      p += "?args[]=" + encodeURIComponent(arguments[1]);
      for (var v = 2; v < arguments.length; v++)
        p += "&args[]=" + encodeURIComponent(arguments[v]);
    }
    return (
      "Minified React error #" +
      y +
      "; visit " +
      p +
      " for the full message or use the non-minified dev environment for full errors and additional helpful warnings."
    );
  }
  function o() {}
  var c = {
      d: {
        f: o,
        r: function () {
          throw Error(u(522));
        },
        D: o,
        C: o,
        L: o,
        m: o,
        X: o,
        S: o,
        M: o,
      },
      p: 0,
      findDOMNode: null,
    },
    f = Symbol.for("react.portal");
  function m(y, p, v) {
    var R =
      3 < arguments.length && arguments[3] !== void 0 ? arguments[3] : null;
    return {
      $$typeof: f,
      key: R == null ? null : "" + R,
      children: y,
      containerInfo: p,
      implementation: v,
    };
  }
  var x = s.__CLIENT_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE;
  function b(y, p) {
    if (y === "font") return "";
    if (typeof p == "string") return p === "use-credentials" ? p : "";
  }
  return (
    (Ie.__DOM_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE = c),
    (Ie.createPortal = function (y, p) {
      var v =
        2 < arguments.length && arguments[2] !== void 0 ? arguments[2] : null;
      if (!p || (p.nodeType !== 1 && p.nodeType !== 9 && p.nodeType !== 11))
        throw Error(u(299));
      return m(y, p, null, v);
    }),
    (Ie.flushSync = function (y) {
      var p = x.T,
        v = c.p;
      try {
        if (((x.T = null), (c.p = 2), y)) return y();
      } finally {
        (x.T = p), (c.p = v), c.d.f();
      }
    }),
    (Ie.preconnect = function (y, p) {
      typeof y == "string" &&
        (p
          ? ((p = p.crossOrigin),
            (p =
              typeof p == "string"
                ? p === "use-credentials"
                  ? p
                  : ""
                : void 0))
          : (p = null),
        c.d.C(y, p));
    }),
    (Ie.prefetchDNS = function (y) {
      typeof y == "string" && c.d.D(y);
    }),
    (Ie.preinit = function (y, p) {
      if (typeof y == "string" && p && typeof p.as == "string") {
        var v = p.as,
          R = b(v, p.crossOrigin),
          N = typeof p.integrity == "string" ? p.integrity : void 0,
          L = typeof p.fetchPriority == "string" ? p.fetchPriority : void 0;
        v === "style"
          ? c.d.S(y, typeof p.precedence == "string" ? p.precedence : void 0, {
              crossOrigin: R,
              integrity: N,
              fetchPriority: L,
            })
          : v === "script" &&
            c.d.X(y, {
              crossOrigin: R,
              integrity: N,
              fetchPriority: L,
              nonce: typeof p.nonce == "string" ? p.nonce : void 0,
            });
      }
    }),
    (Ie.preinitModule = function (y, p) {
      if (typeof y == "string")
        if (typeof p == "object" && p !== null) {
          if (p.as == null || p.as === "script") {
            var v = b(p.as, p.crossOrigin);
            c.d.M(y, {
              crossOrigin: v,
              integrity: typeof p.integrity == "string" ? p.integrity : void 0,
              nonce: typeof p.nonce == "string" ? p.nonce : void 0,
            });
          }
        } else p == null && c.d.M(y);
    }),
    (Ie.preload = function (y, p) {
      if (
        typeof y == "string" &&
        typeof p == "object" &&
        p !== null &&
        typeof p.as == "string"
      ) {
        var v = p.as,
          R = b(v, p.crossOrigin);
        c.d.L(y, v, {
          crossOrigin: R,
          integrity: typeof p.integrity == "string" ? p.integrity : void 0,
          nonce: typeof p.nonce == "string" ? p.nonce : void 0,
          type: typeof p.type == "string" ? p.type : void 0,
          fetchPriority:
            typeof p.fetchPriority == "string" ? p.fetchPriority : void 0,
          referrerPolicy:
            typeof p.referrerPolicy == "string" ? p.referrerPolicy : void 0,
          imageSrcSet:
            typeof p.imageSrcSet == "string" ? p.imageSrcSet : void 0,
          imageSizes: typeof p.imageSizes == "string" ? p.imageSizes : void 0,
          media: typeof p.media == "string" ? p.media : void 0,
        });
      }
    }),
    (Ie.preloadModule = function (y, p) {
      if (typeof y == "string")
        if (p) {
          var v = b(p.as, p.crossOrigin);
          c.d.m(y, {
            as: typeof p.as == "string" && p.as !== "script" ? p.as : void 0,
            crossOrigin: v,
            integrity: typeof p.integrity == "string" ? p.integrity : void 0,
          });
        } else c.d.m(y);
    }),
    (Ie.requestFormReset = function (y) {
      c.d.r(y);
    }),
    (Ie.unstable_batchedUpdates = function (y, p) {
      return y(p);
    }),
    (Ie.useFormState = function (y, p, v) {
      return x.H.useFormState(y, p, v);
    }),
    (Ie.useFormStatus = function () {
      return x.H.useHostTransitionStatus();
    }),
    (Ie.version = "19.1.0"),
    Ie
  );
}
var Rm;
function xx() {
  if (Rm) return nc.exports;
  Rm = 1;
  function s() {
    if (
      !(
        typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ > "u" ||
        typeof __REACT_DEVTOOLS_GLOBAL_HOOK__.checkDCE != "function"
      )
    )
      try {
        __REACT_DEVTOOLS_GLOBAL_HOOK__.checkDCE(s);
      } catch (u) {
        console.error(u);
      }
  }
  return s(), (nc.exports = px()), nc.exports;
}
/**
 * @license React
 * react-dom-client.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */ var Cm;
function gx() {
  if (Cm) return Vn;
  Cm = 1;
  var s = mx(),
    u = bc(),
    o = xx();
  function c(e) {
    var t = "https://react.dev/errors/" + e;
    if (1 < arguments.length) {
      t += "?args[]=" + encodeURIComponent(arguments[1]);
      for (var a = 2; a < arguments.length; a++)
        t += "&args[]=" + encodeURIComponent(arguments[a]);
    }
    return (
      "Minified React error #" +
      e +
      "; visit " +
      t +
      " for the full message or use the non-minified dev environment for full errors and additional helpful warnings."
    );
  }
  function f(e) {
    return !(!e || (e.nodeType !== 1 && e.nodeType !== 9 && e.nodeType !== 11));
  }
  function m(e) {
    var t = e,
      a = e;
    if (e.alternate) for (; t.return; ) t = t.return;
    else {
      e = t;
      do (t = e), (t.flags & 4098) !== 0 && (a = t.return), (e = t.return);
      while (e);
    }
    return t.tag === 3 ? a : null;
  }
  function x(e) {
    if (e.tag === 13) {
      var t = e.memoizedState;
      if (
        (t === null && ((e = e.alternate), e !== null && (t = e.memoizedState)),
        t !== null)
      )
        return t.dehydrated;
    }
    return null;
  }
  function b(e) {
    if (m(e) !== e) throw Error(c(188));
  }
  function y(e) {
    var t = e.alternate;
    if (!t) {
      if (((t = m(e)), t === null)) throw Error(c(188));
      return t !== e ? null : e;
    }
    for (var a = e, l = t; ; ) {
      var n = a.return;
      if (n === null) break;
      var i = n.alternate;
      if (i === null) {
        if (((l = n.return), l !== null)) {
          a = l;
          continue;
        }
        break;
      }
      if (n.child === i.child) {
        for (i = n.child; i; ) {
          if (i === a) return b(n), e;
          if (i === l) return b(n), t;
          i = i.sibling;
        }
        throw Error(c(188));
      }
      if (a.return !== l.return) (a = n), (l = i);
      else {
        for (var d = !1, h = n.child; h; ) {
          if (h === a) {
            (d = !0), (a = n), (l = i);
            break;
          }
          if (h === l) {
            (d = !0), (l = n), (a = i);
            break;
          }
          h = h.sibling;
        }
        if (!d) {
          for (h = i.child; h; ) {
            if (h === a) {
              (d = !0), (a = i), (l = n);
              break;
            }
            if (h === l) {
              (d = !0), (l = i), (a = n);
              break;
            }
            h = h.sibling;
          }
          if (!d) throw Error(c(189));
        }
      }
      if (a.alternate !== l) throw Error(c(190));
    }
    if (a.tag !== 3) throw Error(c(188));
    return a.stateNode.current === a ? e : t;
  }
  function p(e) {
    var t = e.tag;
    if (t === 5 || t === 26 || t === 27 || t === 6) return e;
    for (e = e.child; e !== null; ) {
      if (((t = p(e)), t !== null)) return t;
      e = e.sibling;
    }
    return null;
  }
  var v = Object.assign,
    R = Symbol.for("react.element"),
    N = Symbol.for("react.transitional.element"),
    L = Symbol.for("react.portal"),
    w = Symbol.for("react.fragment"),
    C = Symbol.for("react.strict_mode"),
    D = Symbol.for("react.profiler"),
    Y = Symbol.for("react.provider"),
    Z = Symbol.for("react.consumer"),
    U = Symbol.for("react.context"),
    q = Symbol.for("react.forward_ref"),
    Q = Symbol.for("react.suspense"),
    W = Symbol.for("react.suspense_list"),
    re = Symbol.for("react.memo"),
    ue = Symbol.for("react.lazy"),
    ce = Symbol.for("react.activity"),
    je = Symbol.for("react.memo_cache_sentinel"),
    Ue = Symbol.iterator;
  function Me(e) {
    return e === null || typeof e != "object"
      ? null
      : ((e = (Ue && e[Ue]) || e["@@iterator"]),
        typeof e == "function" ? e : null);
  }
  var F = Symbol.for("react.client.reference");
  function oe(e) {
    if (e == null) return null;
    if (typeof e == "function")
      return e.$$typeof === F ? null : e.displayName || e.name || null;
    if (typeof e == "string") return e;
    switch (e) {
      case w:
        return "Fragment";
      case D:
        return "Profiler";
      case C:
        return "StrictMode";
      case Q:
        return "Suspense";
      case W:
        return "SuspenseList";
      case ce:
        return "Activity";
    }
    if (typeof e == "object")
      switch (e.$$typeof) {
        case L:
          return "Portal";
        case U:
          return (e.displayName || "Context") + ".Provider";
        case Z:
          return (e._context.displayName || "Context") + ".Consumer";
        case q:
          var t = e.render;
          return (
            (e = e.displayName),
            e ||
              ((e = t.displayName || t.name || ""),
              (e = e !== "" ? "ForwardRef(" + e + ")" : "ForwardRef")),
            e
          );
        case re:
          return (
            (t = e.displayName || null), t !== null ? t : oe(e.type) || "Memo"
          );
        case ue:
          (t = e._payload), (e = e._init);
          try {
            return oe(e(t));
          } catch {}
      }
    return null;
  }
  var pe = Array.isArray,
    S = u.__CLIENT_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE,
    H = o.__DOM_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE,
    $ = { pending: !1, data: null, method: null, action: null },
    ee = [],
    g = -1;
  function M(e) {
    return { current: e };
  }
  function J(e) {
    0 > g || ((e.current = ee[g]), (ee[g] = null), g--);
  }
  function K(e, t) {
    g++, (ee[g] = e.current), (e.current = t);
  }
  var P = M(null),
    le = M(null),
    te = M(null),
    tt = M(null);
  function Re(e, t) {
    switch ((K(te, t), K(le, e), K(P, null), t.nodeType)) {
      case 9:
      case 11:
        e = (e = t.documentElement) && (e = e.namespaceURI) ? Fd(e) : 0;
        break;
      default:
        if (((e = t.tagName), (t = t.namespaceURI)))
          (t = Fd(t)), (e = Wd(t, e));
        else
          switch (e) {
            case "svg":
              e = 1;
              break;
            case "math":
              e = 2;
              break;
            default:
              e = 0;
          }
    }
    J(P), K(P, e);
  }
  function ia() {
    J(P), J(le), J(te);
  }
  function Bi(e) {
    e.memoizedState !== null && K(tt, e);
    var t = P.current,
      a = Wd(t, e.type);
    t !== a && (K(le, e), K(P, a));
  }
  function es(e) {
    le.current === e && (J(P), J(le)),
      tt.current === e && (J(tt), (kn._currentValue = $));
  }
  var Hi = Object.prototype.hasOwnProperty,
    qi = s.unstable_scheduleCallback,
    Yi = s.unstable_cancelCallback,
    Vh = s.unstable_shouldYield,
    Gh = s.unstable_requestPaint,
    Dt = s.unstable_now,
    Xh = s.unstable_getCurrentPriorityLevel,
    Ac = s.unstable_ImmediatePriority,
    Rc = s.unstable_UserBlockingPriority,
    ts = s.unstable_NormalPriority,
    Qh = s.unstable_LowPriority,
    Cc = s.unstable_IdlePriority,
    Zh = s.log,
    Kh = s.unstable_setDisableYieldValue,
    Gl = null,
    ot = null;
  function ra(e) {
    if (
      (typeof Zh == "function" && Kh(e),
      ot && typeof ot.setStrictMode == "function")
    )
      try {
        ot.setStrictMode(Gl, e);
      } catch {}
  }
  var ft = Math.clz32 ? Math.clz32 : Fh,
    Jh = Math.log,
    $h = Math.LN2;
  function Fh(e) {
    return (e >>>= 0), e === 0 ? 32 : (31 - ((Jh(e) / $h) | 0)) | 0;
  }
  var as = 256,
    ls = 4194304;
  function Ma(e) {
    var t = e & 42;
    if (t !== 0) return t;
    switch (e & -e) {
      case 1:
        return 1;
      case 2:
        return 2;
      case 4:
        return 4;
      case 8:
        return 8;
      case 16:
        return 16;
      case 32:
        return 32;
      case 64:
        return 64;
      case 128:
        return 128;
      case 256:
      case 512:
      case 1024:
      case 2048:
      case 4096:
      case 8192:
      case 16384:
      case 32768:
      case 65536:
      case 131072:
      case 262144:
      case 524288:
      case 1048576:
      case 2097152:
        return e & 4194048;
      case 4194304:
      case 8388608:
      case 16777216:
      case 33554432:
        return e & 62914560;
      case 67108864:
        return 67108864;
      case 134217728:
        return 134217728;
      case 268435456:
        return 268435456;
      case 536870912:
        return 536870912;
      case 1073741824:
        return 0;
      default:
        return e;
    }
  }
  function ns(e, t, a) {
    var l = e.pendingLanes;
    if (l === 0) return 0;
    var n = 0,
      i = e.suspendedLanes,
      d = e.pingedLanes;
    e = e.warmLanes;
    var h = l & 134217727;
    return (
      h !== 0
        ? ((l = h & ~i),
          l !== 0
            ? (n = Ma(l))
            : ((d &= h),
              d !== 0
                ? (n = Ma(d))
                : a || ((a = h & ~e), a !== 0 && (n = Ma(a)))))
        : ((h = l & ~i),
          h !== 0
            ? (n = Ma(h))
            : d !== 0
            ? (n = Ma(d))
            : a || ((a = l & ~e), a !== 0 && (n = Ma(a)))),
      n === 0
        ? 0
        : t !== 0 &&
          t !== n &&
          (t & i) === 0 &&
          ((i = n & -n),
          (a = t & -t),
          i >= a || (i === 32 && (a & 4194048) !== 0))
        ? t
        : n
    );
  }
  function Xl(e, t) {
    return (e.pendingLanes & ~(e.suspendedLanes & ~e.pingedLanes) & t) === 0;
  }
  function Wh(e, t) {
    switch (e) {
      case 1:
      case 2:
      case 4:
      case 8:
      case 64:
        return t + 250;
      case 16:
      case 32:
      case 128:
      case 256:
      case 512:
      case 1024:
      case 2048:
      case 4096:
      case 8192:
      case 16384:
      case 32768:
      case 65536:
      case 131072:
      case 262144:
      case 524288:
      case 1048576:
      case 2097152:
        return t + 5e3;
      case 4194304:
      case 8388608:
      case 16777216:
      case 33554432:
        return -1;
      case 67108864:
      case 134217728:
      case 268435456:
      case 536870912:
      case 1073741824:
        return -1;
      default:
        return -1;
    }
  }
  function Oc() {
    var e = as;
    return (as <<= 1), (as & 4194048) === 0 && (as = 256), e;
  }
  function Mc() {
    var e = ls;
    return (ls <<= 1), (ls & 62914560) === 0 && (ls = 4194304), e;
  }
  function Vi(e) {
    for (var t = [], a = 0; 31 > a; a++) t.push(e);
    return t;
  }
  function Ql(e, t) {
    (e.pendingLanes |= t),
      t !== 268435456 &&
        ((e.suspendedLanes = 0), (e.pingedLanes = 0), (e.warmLanes = 0));
  }
  function Ph(e, t, a, l, n, i) {
    var d = e.pendingLanes;
    (e.pendingLanes = a),
      (e.suspendedLanes = 0),
      (e.pingedLanes = 0),
      (e.warmLanes = 0),
      (e.expiredLanes &= a),
      (e.entangledLanes &= a),
      (e.errorRecoveryDisabledLanes &= a),
      (e.shellSuspendCounter = 0);
    var h = e.entanglements,
      j = e.expirationTimes,
      O = e.hiddenUpdates;
    for (a = d & ~a; 0 < a; ) {
      var V = 31 - ft(a),
        X = 1 << V;
      (h[V] = 0), (j[V] = -1);
      var z = O[V];
      if (z !== null)
        for (O[V] = null, V = 0; V < z.length; V++) {
          var k = z[V];
          k !== null && (k.lane &= -536870913);
        }
      a &= ~X;
    }
    l !== 0 && Dc(e, l, 0),
      i !== 0 && n === 0 && e.tag !== 0 && (e.suspendedLanes |= i & ~(d & ~t));
  }
  function Dc(e, t, a) {
    (e.pendingLanes |= t), (e.suspendedLanes &= ~t);
    var l = 31 - ft(t);
    (e.entangledLanes |= t),
      (e.entanglements[l] = e.entanglements[l] | 1073741824 | (a & 4194090));
  }
  function zc(e, t) {
    var a = (e.entangledLanes |= t);
    for (e = e.entanglements; a; ) {
      var l = 31 - ft(a),
        n = 1 << l;
      (n & t) | (e[l] & t) && (e[l] |= t), (a &= ~n);
    }
  }
  function Gi(e) {
    switch (e) {
      case 2:
        e = 1;
        break;
      case 8:
        e = 4;
        break;
      case 32:
        e = 16;
        break;
      case 256:
      case 512:
      case 1024:
      case 2048:
      case 4096:
      case 8192:
      case 16384:
      case 32768:
      case 65536:
      case 131072:
      case 262144:
      case 524288:
      case 1048576:
      case 2097152:
      case 4194304:
      case 8388608:
      case 16777216:
      case 33554432:
        e = 128;
        break;
      case 268435456:
        e = 134217728;
        break;
      default:
        e = 0;
    }
    return e;
  }
  function Xi(e) {
    return (
      (e &= -e),
      2 < e ? (8 < e ? ((e & 134217727) !== 0 ? 32 : 268435456) : 8) : 2
    );
  }
  function Uc() {
    var e = H.p;
    return e !== 0 ? e : ((e = window.event), e === void 0 ? 32 : xm(e.type));
  }
  function Ih(e, t) {
    var a = H.p;
    try {
      return (H.p = e), t();
    } finally {
      H.p = a;
    }
  }
  var ua = Math.random().toString(36).slice(2),
    We = "__reactFiber$" + ua,
    lt = "__reactProps$" + ua,
    el = "__reactContainer$" + ua,
    Qi = "__reactEvents$" + ua,
    e0 = "__reactListeners$" + ua,
    t0 = "__reactHandles$" + ua,
    kc = "__reactResources$" + ua,
    Zl = "__reactMarker$" + ua;
  function Zi(e) {
    delete e[We], delete e[lt], delete e[Qi], delete e[e0], delete e[t0];
  }
  function tl(e) {
    var t = e[We];
    if (t) return t;
    for (var a = e.parentNode; a; ) {
      if ((t = a[el] || a[We])) {
        if (
          ((a = t.alternate),
          t.child !== null || (a !== null && a.child !== null))
        )
          for (e = tm(e); e !== null; ) {
            if ((a = e[We])) return a;
            e = tm(e);
          }
        return t;
      }
      (e = a), (a = e.parentNode);
    }
    return null;
  }
  function al(e) {
    if ((e = e[We] || e[el])) {
      var t = e.tag;
      if (t === 5 || t === 6 || t === 13 || t === 26 || t === 27 || t === 3)
        return e;
    }
    return null;
  }
  function Kl(e) {
    var t = e.tag;
    if (t === 5 || t === 26 || t === 27 || t === 6) return e.stateNode;
    throw Error(c(33));
  }
  function ll(e) {
    var t = e[kc];
    return (
      t ||
        (t = e[kc] =
          { hoistableStyles: new Map(), hoistableScripts: new Map() }),
      t
    );
  }
  function Xe(e) {
    e[Zl] = !0;
  }
  var Lc = new Set(),
    Bc = {};
  function Da(e, t) {
    nl(e, t), nl(e + "Capture", t);
  }
  function nl(e, t) {
    for (Bc[e] = t, e = 0; e < t.length; e++) Lc.add(t[e]);
  }
  var a0 = RegExp(
      "^[:A-Z_a-z\\u00C0-\\u00D6\\u00D8-\\u00F6\\u00F8-\\u02FF\\u0370-\\u037D\\u037F-\\u1FFF\\u200C-\\u200D\\u2070-\\u218F\\u2C00-\\u2FEF\\u3001-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFFD][:A-Z_a-z\\u00C0-\\u00D6\\u00D8-\\u00F6\\u00F8-\\u02FF\\u0370-\\u037D\\u037F-\\u1FFF\\u200C-\\u200D\\u2070-\\u218F\\u2C00-\\u2FEF\\u3001-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFFD\\-.0-9\\u00B7\\u0300-\\u036F\\u203F-\\u2040]*$"
    ),
    Hc = {},
    qc = {};
  function l0(e) {
    return Hi.call(qc, e)
      ? !0
      : Hi.call(Hc, e)
      ? !1
      : a0.test(e)
      ? (qc[e] = !0)
      : ((Hc[e] = !0), !1);
  }
  function ss(e, t, a) {
    if (l0(t))
      if (a === null) e.removeAttribute(t);
      else {
        switch (typeof a) {
          case "undefined":
          case "function":
          case "symbol":
            e.removeAttribute(t);
            return;
          case "boolean":
            var l = t.toLowerCase().slice(0, 5);
            if (l !== "data-" && l !== "aria-") {
              e.removeAttribute(t);
              return;
            }
        }
        e.setAttribute(t, "" + a);
      }
  }
  function is(e, t, a) {
    if (a === null) e.removeAttribute(t);
    else {
      switch (typeof a) {
        case "undefined":
        case "function":
        case "symbol":
        case "boolean":
          e.removeAttribute(t);
          return;
      }
      e.setAttribute(t, "" + a);
    }
  }
  function Vt(e, t, a, l) {
    if (l === null) e.removeAttribute(a);
    else {
      switch (typeof l) {
        case "undefined":
        case "function":
        case "symbol":
        case "boolean":
          e.removeAttribute(a);
          return;
      }
      e.setAttributeNS(t, a, "" + l);
    }
  }
  var Ki, Yc;
  function sl(e) {
    if (Ki === void 0)
      try {
        throw Error();
      } catch (a) {
        var t = a.stack.trim().match(/\n( *(at )?)/);
        (Ki = (t && t[1]) || ""),
          (Yc =
            -1 <
            a.stack.indexOf(`
    at`)
              ? " (<anonymous>)"
              : -1 < a.stack.indexOf("@")
              ? "@unknown:0:0"
              : "");
      }
    return (
      `
` +
      Ki +
      e +
      Yc
    );
  }
  var Ji = !1;
  function $i(e, t) {
    if (!e || Ji) return "";
    Ji = !0;
    var a = Error.prepareStackTrace;
    Error.prepareStackTrace = void 0;
    try {
      var l = {
        DetermineComponentFrameRoot: function () {
          try {
            if (t) {
              var X = function () {
                throw Error();
              };
              if (
                (Object.defineProperty(X.prototype, "props", {
                  set: function () {
                    throw Error();
                  },
                }),
                typeof Reflect == "object" && Reflect.construct)
              ) {
                try {
                  Reflect.construct(X, []);
                } catch (k) {
                  var z = k;
                }
                Reflect.construct(e, [], X);
              } else {
                try {
                  X.call();
                } catch (k) {
                  z = k;
                }
                e.call(X.prototype);
              }
            } else {
              try {
                throw Error();
              } catch (k) {
                z = k;
              }
              (X = e()) &&
                typeof X.catch == "function" &&
                X.catch(function () {});
            }
          } catch (k) {
            if (k && z && typeof k.stack == "string") return [k.stack, z.stack];
          }
          return [null, null];
        },
      };
      l.DetermineComponentFrameRoot.displayName = "DetermineComponentFrameRoot";
      var n = Object.getOwnPropertyDescriptor(
        l.DetermineComponentFrameRoot,
        "name"
      );
      n &&
        n.configurable &&
        Object.defineProperty(l.DetermineComponentFrameRoot, "name", {
          value: "DetermineComponentFrameRoot",
        });
      var i = l.DetermineComponentFrameRoot(),
        d = i[0],
        h = i[1];
      if (d && h) {
        var j = d.split(`
`),
          O = h.split(`
`);
        for (
          n = l = 0;
          l < j.length && !j[l].includes("DetermineComponentFrameRoot");

        )
          l++;
        for (; n < O.length && !O[n].includes("DetermineComponentFrameRoot"); )
          n++;
        if (l === j.length || n === O.length)
          for (
            l = j.length - 1, n = O.length - 1;
            1 <= l && 0 <= n && j[l] !== O[n];

          )
            n--;
        for (; 1 <= l && 0 <= n; l--, n--)
          if (j[l] !== O[n]) {
            if (l !== 1 || n !== 1)
              do
                if ((l--, n--, 0 > n || j[l] !== O[n])) {
                  var V =
                    `
` + j[l].replace(" at new ", " at ");
                  return (
                    e.displayName &&
                      V.includes("<anonymous>") &&
                      (V = V.replace("<anonymous>", e.displayName)),
                    V
                  );
                }
              while (1 <= l && 0 <= n);
            break;
          }
      }
    } finally {
      (Ji = !1), (Error.prepareStackTrace = a);
    }
    return (a = e ? e.displayName || e.name : "") ? sl(a) : "";
  }
  function n0(e) {
    switch (e.tag) {
      case 26:
      case 27:
      case 5:
        return sl(e.type);
      case 16:
        return sl("Lazy");
      case 13:
        return sl("Suspense");
      case 19:
        return sl("SuspenseList");
      case 0:
      case 15:
        return $i(e.type, !1);
      case 11:
        return $i(e.type.render, !1);
      case 1:
        return $i(e.type, !0);
      case 31:
        return sl("Activity");
      default:
        return "";
    }
  }
  function Vc(e) {
    try {
      var t = "";
      do (t += n0(e)), (e = e.return);
      while (e);
      return t;
    } catch (a) {
      return (
        `
Error generating stack: ` +
        a.message +
        `
` +
        a.stack
      );
    }
  }
  function vt(e) {
    switch (typeof e) {
      case "bigint":
      case "boolean":
      case "number":
      case "string":
      case "undefined":
        return e;
      case "object":
        return e;
      default:
        return "";
    }
  }
  function Gc(e) {
    var t = e.type;
    return (
      (e = e.nodeName) &&
      e.toLowerCase() === "input" &&
      (t === "checkbox" || t === "radio")
    );
  }
  function s0(e) {
    var t = Gc(e) ? "checked" : "value",
      a = Object.getOwnPropertyDescriptor(e.constructor.prototype, t),
      l = "" + e[t];
    if (
      !e.hasOwnProperty(t) &&
      typeof a < "u" &&
      typeof a.get == "function" &&
      typeof a.set == "function"
    ) {
      var n = a.get,
        i = a.set;
      return (
        Object.defineProperty(e, t, {
          configurable: !0,
          get: function () {
            return n.call(this);
          },
          set: function (d) {
            (l = "" + d), i.call(this, d);
          },
        }),
        Object.defineProperty(e, t, { enumerable: a.enumerable }),
        {
          getValue: function () {
            return l;
          },
          setValue: function (d) {
            l = "" + d;
          },
          stopTracking: function () {
            (e._valueTracker = null), delete e[t];
          },
        }
      );
    }
  }
  function rs(e) {
    e._valueTracker || (e._valueTracker = s0(e));
  }
  function Xc(e) {
    if (!e) return !1;
    var t = e._valueTracker;
    if (!t) return !0;
    var a = t.getValue(),
      l = "";
    return (
      e && (l = Gc(e) ? (e.checked ? "true" : "false") : e.value),
      (e = l),
      e !== a ? (t.setValue(e), !0) : !1
    );
  }
  function us(e) {
    if (
      ((e = e || (typeof document < "u" ? document : void 0)), typeof e > "u")
    )
      return null;
    try {
      return e.activeElement || e.body;
    } catch {
      return e.body;
    }
  }
  var i0 = /[\n"\\]/g;
  function jt(e) {
    return e.replace(i0, function (t) {
      return "\\" + t.charCodeAt(0).toString(16) + " ";
    });
  }
  function Fi(e, t, a, l, n, i, d, h) {
    (e.name = ""),
      d != null &&
      typeof d != "function" &&
      typeof d != "symbol" &&
      typeof d != "boolean"
        ? (e.type = d)
        : e.removeAttribute("type"),
      t != null
        ? d === "number"
          ? ((t === 0 && e.value === "") || e.value != t) &&
            (e.value = "" + vt(t))
          : e.value !== "" + vt(t) && (e.value = "" + vt(t))
        : (d !== "submit" && d !== "reset") || e.removeAttribute("value"),
      t != null
        ? Wi(e, d, vt(t))
        : a != null
        ? Wi(e, d, vt(a))
        : l != null && e.removeAttribute("value"),
      n == null && i != null && (e.defaultChecked = !!i),
      n != null &&
        (e.checked = n && typeof n != "function" && typeof n != "symbol"),
      h != null &&
      typeof h != "function" &&
      typeof h != "symbol" &&
      typeof h != "boolean"
        ? (e.name = "" + vt(h))
        : e.removeAttribute("name");
  }
  function Qc(e, t, a, l, n, i, d, h) {
    if (
      (i != null &&
        typeof i != "function" &&
        typeof i != "symbol" &&
        typeof i != "boolean" &&
        (e.type = i),
      t != null || a != null)
    ) {
      if (!((i !== "submit" && i !== "reset") || t != null)) return;
      (a = a != null ? "" + vt(a) : ""),
        (t = t != null ? "" + vt(t) : a),
        h || t === e.value || (e.value = t),
        (e.defaultValue = t);
    }
    (l = l ?? n),
      (l = typeof l != "function" && typeof l != "symbol" && !!l),
      (e.checked = h ? e.checked : !!l),
      (e.defaultChecked = !!l),
      d != null &&
        typeof d != "function" &&
        typeof d != "symbol" &&
        typeof d != "boolean" &&
        (e.name = d);
  }
  function Wi(e, t, a) {
    (t === "number" && us(e.ownerDocument) === e) ||
      e.defaultValue === "" + a ||
      (e.defaultValue = "" + a);
  }
  function il(e, t, a, l) {
    if (((e = e.options), t)) {
      t = {};
      for (var n = 0; n < a.length; n++) t["$" + a[n]] = !0;
      for (a = 0; a < e.length; a++)
        (n = t.hasOwnProperty("$" + e[a].value)),
          e[a].selected !== n && (e[a].selected = n),
          n && l && (e[a].defaultSelected = !0);
    } else {
      for (a = "" + vt(a), t = null, n = 0; n < e.length; n++) {
        if (e[n].value === a) {
          (e[n].selected = !0), l && (e[n].defaultSelected = !0);
          return;
        }
        t !== null || e[n].disabled || (t = e[n]);
      }
      t !== null && (t.selected = !0);
    }
  }
  function Zc(e, t, a) {
    if (
      t != null &&
      ((t = "" + vt(t)), t !== e.value && (e.value = t), a == null)
    ) {
      e.defaultValue !== t && (e.defaultValue = t);
      return;
    }
    e.defaultValue = a != null ? "" + vt(a) : "";
  }
  function Kc(e, t, a, l) {
    if (t == null) {
      if (l != null) {
        if (a != null) throw Error(c(92));
        if (pe(l)) {
          if (1 < l.length) throw Error(c(93));
          l = l[0];
        }
        a = l;
      }
      a == null && (a = ""), (t = a);
    }
    (a = vt(t)),
      (e.defaultValue = a),
      (l = e.textContent),
      l === a && l !== "" && l !== null && (e.value = l);
  }
  function rl(e, t) {
    if (t) {
      var a = e.firstChild;
      if (a && a === e.lastChild && a.nodeType === 3) {
        a.nodeValue = t;
        return;
      }
    }
    e.textContent = t;
  }
  var r0 = new Set(
    "animationIterationCount aspectRatio borderImageOutset borderImageSlice borderImageWidth boxFlex boxFlexGroup boxOrdinalGroup columnCount columns flex flexGrow flexPositive flexShrink flexNegative flexOrder gridArea gridRow gridRowEnd gridRowSpan gridRowStart gridColumn gridColumnEnd gridColumnSpan gridColumnStart fontWeight lineClamp lineHeight opacity order orphans scale tabSize widows zIndex zoom fillOpacity floodOpacity stopOpacity strokeDasharray strokeDashoffset strokeMiterlimit strokeOpacity strokeWidth MozAnimationIterationCount MozBoxFlex MozBoxFlexGroup MozLineClamp msAnimationIterationCount msFlex msZoom msFlexGrow msFlexNegative msFlexOrder msFlexPositive msFlexShrink msGridColumn msGridColumnSpan msGridRow msGridRowSpan WebkitAnimationIterationCount WebkitBoxFlex WebKitBoxFlexGroup WebkitBoxOrdinalGroup WebkitColumnCount WebkitColumns WebkitFlex WebkitFlexGrow WebkitFlexPositive WebkitFlexShrink WebkitLineClamp".split(
      " "
    )
  );
  function Jc(e, t, a) {
    var l = t.indexOf("--") === 0;
    a == null || typeof a == "boolean" || a === ""
      ? l
        ? e.setProperty(t, "")
        : t === "float"
        ? (e.cssFloat = "")
        : (e[t] = "")
      : l
      ? e.setProperty(t, a)
      : typeof a != "number" || a === 0 || r0.has(t)
      ? t === "float"
        ? (e.cssFloat = a)
        : (e[t] = ("" + a).trim())
      : (e[t] = a + "px");
  }
  function $c(e, t, a) {
    if (t != null && typeof t != "object") throw Error(c(62));
    if (((e = e.style), a != null)) {
      for (var l in a)
        !a.hasOwnProperty(l) ||
          (t != null && t.hasOwnProperty(l)) ||
          (l.indexOf("--") === 0
            ? e.setProperty(l, "")
            : l === "float"
            ? (e.cssFloat = "")
            : (e[l] = ""));
      for (var n in t)
        (l = t[n]), t.hasOwnProperty(n) && a[n] !== l && Jc(e, n, l);
    } else for (var i in t) t.hasOwnProperty(i) && Jc(e, i, t[i]);
  }
  function Pi(e) {
    if (e.indexOf("-") === -1) return !1;
    switch (e) {
      case "annotation-xml":
      case "color-profile":
      case "font-face":
      case "font-face-src":
      case "font-face-uri":
      case "font-face-format":
      case "font-face-name":
      case "missing-glyph":
        return !1;
      default:
        return !0;
    }
  }
  var u0 = new Map([
      ["acceptCharset", "accept-charset"],
      ["htmlFor", "for"],
      ["httpEquiv", "http-equiv"],
      ["crossOrigin", "crossorigin"],
      ["accentHeight", "accent-height"],
      ["alignmentBaseline", "alignment-baseline"],
      ["arabicForm", "arabic-form"],
      ["baselineShift", "baseline-shift"],
      ["capHeight", "cap-height"],
      ["clipPath", "clip-path"],
      ["clipRule", "clip-rule"],
      ["colorInterpolation", "color-interpolation"],
      ["colorInterpolationFilters", "color-interpolation-filters"],
      ["colorProfile", "color-profile"],
      ["colorRendering", "color-rendering"],
      ["dominantBaseline", "dominant-baseline"],
      ["enableBackground", "enable-background"],
      ["fillOpacity", "fill-opacity"],
      ["fillRule", "fill-rule"],
      ["floodColor", "flood-color"],
      ["floodOpacity", "flood-opacity"],
      ["fontFamily", "font-family"],
      ["fontSize", "font-size"],
      ["fontSizeAdjust", "font-size-adjust"],
      ["fontStretch", "font-stretch"],
      ["fontStyle", "font-style"],
      ["fontVariant", "font-variant"],
      ["fontWeight", "font-weight"],
      ["glyphName", "glyph-name"],
      ["glyphOrientationHorizontal", "glyph-orientation-horizontal"],
      ["glyphOrientationVertical", "glyph-orientation-vertical"],
      ["horizAdvX", "horiz-adv-x"],
      ["horizOriginX", "horiz-origin-x"],
      ["imageRendering", "image-rendering"],
      ["letterSpacing", "letter-spacing"],
      ["lightingColor", "lighting-color"],
      ["markerEnd", "marker-end"],
      ["markerMid", "marker-mid"],
      ["markerStart", "marker-start"],
      ["overlinePosition", "overline-position"],
      ["overlineThickness", "overline-thickness"],
      ["paintOrder", "paint-order"],
      ["panose-1", "panose-1"],
      ["pointerEvents", "pointer-events"],
      ["renderingIntent", "rendering-intent"],
      ["shapeRendering", "shape-rendering"],
      ["stopColor", "stop-color"],
      ["stopOpacity", "stop-opacity"],
      ["strikethroughPosition", "strikethrough-position"],
      ["strikethroughThickness", "strikethrough-thickness"],
      ["strokeDasharray", "stroke-dasharray"],
      ["strokeDashoffset", "stroke-dashoffset"],
      ["strokeLinecap", "stroke-linecap"],
      ["strokeLinejoin", "stroke-linejoin"],
      ["strokeMiterlimit", "stroke-miterlimit"],
      ["strokeOpacity", "stroke-opacity"],
      ["strokeWidth", "stroke-width"],
      ["textAnchor", "text-anchor"],
      ["textDecoration", "text-decoration"],
      ["textRendering", "text-rendering"],
      ["transformOrigin", "transform-origin"],
      ["underlinePosition", "underline-position"],
      ["underlineThickness", "underline-thickness"],
      ["unicodeBidi", "unicode-bidi"],
      ["unicodeRange", "unicode-range"],
      ["unitsPerEm", "units-per-em"],
      ["vAlphabetic", "v-alphabetic"],
      ["vHanging", "v-hanging"],
      ["vIdeographic", "v-ideographic"],
      ["vMathematical", "v-mathematical"],
      ["vectorEffect", "vector-effect"],
      ["vertAdvY", "vert-adv-y"],
      ["vertOriginX", "vert-origin-x"],
      ["vertOriginY", "vert-origin-y"],
      ["wordSpacing", "word-spacing"],
      ["writingMode", "writing-mode"],
      ["xmlnsXlink", "xmlns:xlink"],
      ["xHeight", "x-height"],
    ]),
    c0 =
      /^[\u0000-\u001F ]*j[\r\n\t]*a[\r\n\t]*v[\r\n\t]*a[\r\n\t]*s[\r\n\t]*c[\r\n\t]*r[\r\n\t]*i[\r\n\t]*p[\r\n\t]*t[\r\n\t]*:/i;
  function cs(e) {
    return c0.test("" + e)
      ? "javascript:throw new Error('React has blocked a javascript: URL as a security precaution.')"
      : e;
  }
  var Ii = null;
  function er(e) {
    return (
      (e = e.target || e.srcElement || window),
      e.correspondingUseElement && (e = e.correspondingUseElement),
      e.nodeType === 3 ? e.parentNode : e
    );
  }
  var ul = null,
    cl = null;
  function Fc(e) {
    var t = al(e);
    if (t && (e = t.stateNode)) {
      var a = e[lt] || null;
      e: switch (((e = t.stateNode), t.type)) {
        case "input":
          if (
            (Fi(
              e,
              a.value,
              a.defaultValue,
              a.defaultValue,
              a.checked,
              a.defaultChecked,
              a.type,
              a.name
            ),
            (t = a.name),
            a.type === "radio" && t != null)
          ) {
            for (a = e; a.parentNode; ) a = a.parentNode;
            for (
              a = a.querySelectorAll(
                'input[name="' + jt("" + t) + '"][type="radio"]'
              ),
                t = 0;
              t < a.length;
              t++
            ) {
              var l = a[t];
              if (l !== e && l.form === e.form) {
                var n = l[lt] || null;
                if (!n) throw Error(c(90));
                Fi(
                  l,
                  n.value,
                  n.defaultValue,
                  n.defaultValue,
                  n.checked,
                  n.defaultChecked,
                  n.type,
                  n.name
                );
              }
            }
            for (t = 0; t < a.length; t++)
              (l = a[t]), l.form === e.form && Xc(l);
          }
          break e;
        case "textarea":
          Zc(e, a.value, a.defaultValue);
          break e;
        case "select":
          (t = a.value), t != null && il(e, !!a.multiple, t, !1);
      }
    }
  }
  var tr = !1;
  function Wc(e, t, a) {
    if (tr) return e(t, a);
    tr = !0;
    try {
      var l = e(t);
      return l;
    } finally {
      if (
        ((tr = !1),
        (ul !== null || cl !== null) &&
          (Js(), ul && ((t = ul), (e = cl), (cl = ul = null), Fc(t), e)))
      )
        for (t = 0; t < e.length; t++) Fc(e[t]);
    }
  }
  function Jl(e, t) {
    var a = e.stateNode;
    if (a === null) return null;
    var l = a[lt] || null;
    if (l === null) return null;
    a = l[t];
    e: switch (t) {
      case "onClick":
      case "onClickCapture":
      case "onDoubleClick":
      case "onDoubleClickCapture":
      case "onMouseDown":
      case "onMouseDownCapture":
      case "onMouseMove":
      case "onMouseMoveCapture":
      case "onMouseUp":
      case "onMouseUpCapture":
      case "onMouseEnter":
        (l = !l.disabled) ||
          ((e = e.type),
          (l = !(
            e === "button" ||
            e === "input" ||
            e === "select" ||
            e === "textarea"
          ))),
          (e = !l);
        break e;
      default:
        e = !1;
    }
    if (e) return null;
    if (a && typeof a != "function") throw Error(c(231, t, typeof a));
    return a;
  }
  var Gt = !(
      typeof window > "u" ||
      typeof window.document > "u" ||
      typeof window.document.createElement > "u"
    ),
    ar = !1;
  if (Gt)
    try {
      var $l = {};
      Object.defineProperty($l, "passive", {
        get: function () {
          ar = !0;
        },
      }),
        window.addEventListener("test", $l, $l),
        window.removeEventListener("test", $l, $l);
    } catch {
      ar = !1;
    }
  var ca = null,
    lr = null,
    os = null;
  function Pc() {
    if (os) return os;
    var e,
      t = lr,
      a = t.length,
      l,
      n = "value" in ca ? ca.value : ca.textContent,
      i = n.length;
    for (e = 0; e < a && t[e] === n[e]; e++);
    var d = a - e;
    for (l = 1; l <= d && t[a - l] === n[i - l]; l++);
    return (os = n.slice(e, 1 < l ? 1 - l : void 0));
  }
  function fs(e) {
    var t = e.keyCode;
    return (
      "charCode" in e
        ? ((e = e.charCode), e === 0 && t === 13 && (e = 13))
        : (e = t),
      e === 10 && (e = 13),
      32 <= e || e === 13 ? e : 0
    );
  }
  function ds() {
    return !0;
  }
  function Ic() {
    return !1;
  }
  function nt(e) {
    function t(a, l, n, i, d) {
      (this._reactName = a),
        (this._targetInst = n),
        (this.type = l),
        (this.nativeEvent = i),
        (this.target = d),
        (this.currentTarget = null);
      for (var h in e)
        e.hasOwnProperty(h) && ((a = e[h]), (this[h] = a ? a(i) : i[h]));
      return (
        (this.isDefaultPrevented = (
          i.defaultPrevented != null ? i.defaultPrevented : i.returnValue === !1
        )
          ? ds
          : Ic),
        (this.isPropagationStopped = Ic),
        this
      );
    }
    return (
      v(t.prototype, {
        preventDefault: function () {
          this.defaultPrevented = !0;
          var a = this.nativeEvent;
          a &&
            (a.preventDefault
              ? a.preventDefault()
              : typeof a.returnValue != "unknown" && (a.returnValue = !1),
            (this.isDefaultPrevented = ds));
        },
        stopPropagation: function () {
          var a = this.nativeEvent;
          a &&
            (a.stopPropagation
              ? a.stopPropagation()
              : typeof a.cancelBubble != "unknown" && (a.cancelBubble = !0),
            (this.isPropagationStopped = ds));
        },
        persist: function () {},
        isPersistent: ds,
      }),
      t
    );
  }
  var za = {
      eventPhase: 0,
      bubbles: 0,
      cancelable: 0,
      timeStamp: function (e) {
        return e.timeStamp || Date.now();
      },
      defaultPrevented: 0,
      isTrusted: 0,
    },
    ms = nt(za),
    Fl = v({}, za, { view: 0, detail: 0 }),
    o0 = nt(Fl),
    nr,
    sr,
    Wl,
    hs = v({}, Fl, {
      screenX: 0,
      screenY: 0,
      clientX: 0,
      clientY: 0,
      pageX: 0,
      pageY: 0,
      ctrlKey: 0,
      shiftKey: 0,
      altKey: 0,
      metaKey: 0,
      getModifierState: rr,
      button: 0,
      buttons: 0,
      relatedTarget: function (e) {
        return e.relatedTarget === void 0
          ? e.fromElement === e.srcElement
            ? e.toElement
            : e.fromElement
          : e.relatedTarget;
      },
      movementX: function (e) {
        return "movementX" in e
          ? e.movementX
          : (e !== Wl &&
              (Wl && e.type === "mousemove"
                ? ((nr = e.screenX - Wl.screenX), (sr = e.screenY - Wl.screenY))
                : (sr = nr = 0),
              (Wl = e)),
            nr);
      },
      movementY: function (e) {
        return "movementY" in e ? e.movementY : sr;
      },
    }),
    eo = nt(hs),
    f0 = v({}, hs, { dataTransfer: 0 }),
    d0 = nt(f0),
    m0 = v({}, Fl, { relatedTarget: 0 }),
    ir = nt(m0),
    h0 = v({}, za, { animationName: 0, elapsedTime: 0, pseudoElement: 0 }),
    p0 = nt(h0),
    x0 = v({}, za, {
      clipboardData: function (e) {
        return "clipboardData" in e ? e.clipboardData : window.clipboardData;
      },
    }),
    g0 = nt(x0),
    y0 = v({}, za, { data: 0 }),
    to = nt(y0),
    b0 = {
      Esc: "Escape",
      Spacebar: " ",
      Left: "ArrowLeft",
      Up: "ArrowUp",
      Right: "ArrowRight",
      Down: "ArrowDown",
      Del: "Delete",
      Win: "OS",
      Menu: "ContextMenu",
      Apps: "ContextMenu",
      Scroll: "ScrollLock",
      MozPrintableKey: "Unidentified",
    },
    v0 = {
      8: "Backspace",
      9: "Tab",
      12: "Clear",
      13: "Enter",
      16: "Shift",
      17: "Control",
      18: "Alt",
      19: "Pause",
      20: "CapsLock",
      27: "Escape",
      32: " ",
      33: "PageUp",
      34: "PageDown",
      35: "End",
      36: "Home",
      37: "ArrowLeft",
      38: "ArrowUp",
      39: "ArrowRight",
      40: "ArrowDown",
      45: "Insert",
      46: "Delete",
      112: "F1",
      113: "F2",
      114: "F3",
      115: "F4",
      116: "F5",
      117: "F6",
      118: "F7",
      119: "F8",
      120: "F9",
      121: "F10",
      122: "F11",
      123: "F12",
      144: "NumLock",
      145: "ScrollLock",
      224: "Meta",
    },
    j0 = {
      Alt: "altKey",
      Control: "ctrlKey",
      Meta: "metaKey",
      Shift: "shiftKey",
    };
  function S0(e) {
    var t = this.nativeEvent;
    return t.getModifierState
      ? t.getModifierState(e)
      : (e = j0[e])
      ? !!t[e]
      : !1;
  }
  function rr() {
    return S0;
  }
  var N0 = v({}, Fl, {
      key: function (e) {
        if (e.key) {
          var t = b0[e.key] || e.key;
          if (t !== "Unidentified") return t;
        }
        return e.type === "keypress"
          ? ((e = fs(e)), e === 13 ? "Enter" : String.fromCharCode(e))
          : e.type === "keydown" || e.type === "keyup"
          ? v0[e.keyCode] || "Unidentified"
          : "";
      },
      code: 0,
      location: 0,
      ctrlKey: 0,
      shiftKey: 0,
      altKey: 0,
      metaKey: 0,
      repeat: 0,
      locale: 0,
      getModifierState: rr,
      charCode: function (e) {
        return e.type === "keypress" ? fs(e) : 0;
      },
      keyCode: function (e) {
        return e.type === "keydown" || e.type === "keyup" ? e.keyCode : 0;
      },
      which: function (e) {
        return e.type === "keypress"
          ? fs(e)
          : e.type === "keydown" || e.type === "keyup"
          ? e.keyCode
          : 0;
      },
    }),
    w0 = nt(N0),
    E0 = v({}, hs, {
      pointerId: 0,
      width: 0,
      height: 0,
      pressure: 0,
      tangentialPressure: 0,
      tiltX: 0,
      tiltY: 0,
      twist: 0,
      pointerType: 0,
      isPrimary: 0,
    }),
    ao = nt(E0),
    T0 = v({}, Fl, {
      touches: 0,
      targetTouches: 0,
      changedTouches: 0,
      altKey: 0,
      metaKey: 0,
      ctrlKey: 0,
      shiftKey: 0,
      getModifierState: rr,
    }),
    _0 = nt(T0),
    A0 = v({}, za, { propertyName: 0, elapsedTime: 0, pseudoElement: 0 }),
    R0 = nt(A0),
    C0 = v({}, hs, {
      deltaX: function (e) {
        return "deltaX" in e
          ? e.deltaX
          : "wheelDeltaX" in e
          ? -e.wheelDeltaX
          : 0;
      },
      deltaY: function (e) {
        return "deltaY" in e
          ? e.deltaY
          : "wheelDeltaY" in e
          ? -e.wheelDeltaY
          : "wheelDelta" in e
          ? -e.wheelDelta
          : 0;
      },
      deltaZ: 0,
      deltaMode: 0,
    }),
    O0 = nt(C0),
    M0 = v({}, za, { newState: 0, oldState: 0 }),
    D0 = nt(M0),
    z0 = [9, 13, 27, 32],
    ur = Gt && "CompositionEvent" in window,
    Pl = null;
  Gt && "documentMode" in document && (Pl = document.documentMode);
  var U0 = Gt && "TextEvent" in window && !Pl,
    lo = Gt && (!ur || (Pl && 8 < Pl && 11 >= Pl)),
    no = " ",
    so = !1;
  function io(e, t) {
    switch (e) {
      case "keyup":
        return z0.indexOf(t.keyCode) !== -1;
      case "keydown":
        return t.keyCode !== 229;
      case "keypress":
      case "mousedown":
      case "focusout":
        return !0;
      default:
        return !1;
    }
  }
  function ro(e) {
    return (e = e.detail), typeof e == "object" && "data" in e ? e.data : null;
  }
  var ol = !1;
  function k0(e, t) {
    switch (e) {
      case "compositionend":
        return ro(t);
      case "keypress":
        return t.which !== 32 ? null : ((so = !0), no);
      case "textInput":
        return (e = t.data), e === no && so ? null : e;
      default:
        return null;
    }
  }
  function L0(e, t) {
    if (ol)
      return e === "compositionend" || (!ur && io(e, t))
        ? ((e = Pc()), (os = lr = ca = null), (ol = !1), e)
        : null;
    switch (e) {
      case "paste":
        return null;
      case "keypress":
        if (!(t.ctrlKey || t.altKey || t.metaKey) || (t.ctrlKey && t.altKey)) {
          if (t.char && 1 < t.char.length) return t.char;
          if (t.which) return String.fromCharCode(t.which);
        }
        return null;
      case "compositionend":
        return lo && t.locale !== "ko" ? null : t.data;
      default:
        return null;
    }
  }
  var B0 = {
    color: !0,
    date: !0,
    datetime: !0,
    "datetime-local": !0,
    email: !0,
    month: !0,
    number: !0,
    password: !0,
    range: !0,
    search: !0,
    tel: !0,
    text: !0,
    time: !0,
    url: !0,
    week: !0,
  };
  function uo(e) {
    var t = e && e.nodeName && e.nodeName.toLowerCase();
    return t === "input" ? !!B0[e.type] : t === "textarea";
  }
  function co(e, t, a, l) {
    ul ? (cl ? cl.push(l) : (cl = [l])) : (ul = l),
      (t = ei(t, "onChange")),
      0 < t.length &&
        ((a = new ms("onChange", "change", null, a, l)),
        e.push({ event: a, listeners: t }));
  }
  var Il = null,
    en = null;
  function H0(e) {
    Qd(e, 0);
  }
  function ps(e) {
    var t = Kl(e);
    if (Xc(t)) return e;
  }
  function oo(e, t) {
    if (e === "change") return t;
  }
  var fo = !1;
  if (Gt) {
    var cr;
    if (Gt) {
      var or = "oninput" in document;
      if (!or) {
        var mo = document.createElement("div");
        mo.setAttribute("oninput", "return;"),
          (or = typeof mo.oninput == "function");
      }
      cr = or;
    } else cr = !1;
    fo = cr && (!document.documentMode || 9 < document.documentMode);
  }
  function ho() {
    Il && (Il.detachEvent("onpropertychange", po), (en = Il = null));
  }
  function po(e) {
    if (e.propertyName === "value" && ps(en)) {
      var t = [];
      co(t, en, e, er(e)), Wc(H0, t);
    }
  }
  function q0(e, t, a) {
    e === "focusin"
      ? (ho(), (Il = t), (en = a), Il.attachEvent("onpropertychange", po))
      : e === "focusout" && ho();
  }
  function Y0(e) {
    if (e === "selectionchange" || e === "keyup" || e === "keydown")
      return ps(en);
  }
  function V0(e, t) {
    if (e === "click") return ps(t);
  }
  function G0(e, t) {
    if (e === "input" || e === "change") return ps(t);
  }
  function X0(e, t) {
    return (e === t && (e !== 0 || 1 / e === 1 / t)) || (e !== e && t !== t);
  }
  var dt = typeof Object.is == "function" ? Object.is : X0;
  function tn(e, t) {
    if (dt(e, t)) return !0;
    if (
      typeof e != "object" ||
      e === null ||
      typeof t != "object" ||
      t === null
    )
      return !1;
    var a = Object.keys(e),
      l = Object.keys(t);
    if (a.length !== l.length) return !1;
    for (l = 0; l < a.length; l++) {
      var n = a[l];
      if (!Hi.call(t, n) || !dt(e[n], t[n])) return !1;
    }
    return !0;
  }
  function xo(e) {
    for (; e && e.firstChild; ) e = e.firstChild;
    return e;
  }
  function go(e, t) {
    var a = xo(e);
    e = 0;
    for (var l; a; ) {
      if (a.nodeType === 3) {
        if (((l = e + a.textContent.length), e <= t && l >= t))
          return { node: a, offset: t - e };
        e = l;
      }
      e: {
        for (; a; ) {
          if (a.nextSibling) {
            a = a.nextSibling;
            break e;
          }
          a = a.parentNode;
        }
        a = void 0;
      }
      a = xo(a);
    }
  }
  function yo(e, t) {
    return e && t
      ? e === t
        ? !0
        : e && e.nodeType === 3
        ? !1
        : t && t.nodeType === 3
        ? yo(e, t.parentNode)
        : "contains" in e
        ? e.contains(t)
        : e.compareDocumentPosition
        ? !!(e.compareDocumentPosition(t) & 16)
        : !1
      : !1;
  }
  function bo(e) {
    e =
      e != null &&
      e.ownerDocument != null &&
      e.ownerDocument.defaultView != null
        ? e.ownerDocument.defaultView
        : window;
    for (var t = us(e.document); t instanceof e.HTMLIFrameElement; ) {
      try {
        var a = typeof t.contentWindow.location.href == "string";
      } catch {
        a = !1;
      }
      if (a) e = t.contentWindow;
      else break;
      t = us(e.document);
    }
    return t;
  }
  function fr(e) {
    var t = e && e.nodeName && e.nodeName.toLowerCase();
    return (
      t &&
      ((t === "input" &&
        (e.type === "text" ||
          e.type === "search" ||
          e.type === "tel" ||
          e.type === "url" ||
          e.type === "password")) ||
        t === "textarea" ||
        e.contentEditable === "true")
    );
  }
  var Q0 = Gt && "documentMode" in document && 11 >= document.documentMode,
    fl = null,
    dr = null,
    an = null,
    mr = !1;
  function vo(e, t, a) {
    var l =
      a.window === a ? a.document : a.nodeType === 9 ? a : a.ownerDocument;
    mr ||
      fl == null ||
      fl !== us(l) ||
      ((l = fl),
      "selectionStart" in l && fr(l)
        ? (l = { start: l.selectionStart, end: l.selectionEnd })
        : ((l = (
            (l.ownerDocument && l.ownerDocument.defaultView) ||
            window
          ).getSelection()),
          (l = {
            anchorNode: l.anchorNode,
            anchorOffset: l.anchorOffset,
            focusNode: l.focusNode,
            focusOffset: l.focusOffset,
          })),
      (an && tn(an, l)) ||
        ((an = l),
        (l = ei(dr, "onSelect")),
        0 < l.length &&
          ((t = new ms("onSelect", "select", null, t, a)),
          e.push({ event: t, listeners: l }),
          (t.target = fl))));
  }
  function Ua(e, t) {
    var a = {};
    return (
      (a[e.toLowerCase()] = t.toLowerCase()),
      (a["Webkit" + e] = "webkit" + t),
      (a["Moz" + e] = "moz" + t),
      a
    );
  }
  var dl = {
      animationend: Ua("Animation", "AnimationEnd"),
      animationiteration: Ua("Animation", "AnimationIteration"),
      animationstart: Ua("Animation", "AnimationStart"),
      transitionrun: Ua("Transition", "TransitionRun"),
      transitionstart: Ua("Transition", "TransitionStart"),
      transitioncancel: Ua("Transition", "TransitionCancel"),
      transitionend: Ua("Transition", "TransitionEnd"),
    },
    hr = {},
    jo = {};
  Gt &&
    ((jo = document.createElement("div").style),
    "AnimationEvent" in window ||
      (delete dl.animationend.animation,
      delete dl.animationiteration.animation,
      delete dl.animationstart.animation),
    "TransitionEvent" in window || delete dl.transitionend.transition);
  function ka(e) {
    if (hr[e]) return hr[e];
    if (!dl[e]) return e;
    var t = dl[e],
      a;
    for (a in t) if (t.hasOwnProperty(a) && a in jo) return (hr[e] = t[a]);
    return e;
  }
  var So = ka("animationend"),
    No = ka("animationiteration"),
    wo = ka("animationstart"),
    Z0 = ka("transitionrun"),
    K0 = ka("transitionstart"),
    J0 = ka("transitioncancel"),
    Eo = ka("transitionend"),
    To = new Map(),
    pr =
      "abort auxClick beforeToggle cancel canPlay canPlayThrough click close contextMenu copy cut drag dragEnd dragEnter dragExit dragLeave dragOver dragStart drop durationChange emptied encrypted ended error gotPointerCapture input invalid keyDown keyPress keyUp load loadedData loadedMetadata loadStart lostPointerCapture mouseDown mouseMove mouseOut mouseOver mouseUp paste pause play playing pointerCancel pointerDown pointerMove pointerOut pointerOver pointerUp progress rateChange reset resize seeked seeking stalled submit suspend timeUpdate touchCancel touchEnd touchStart volumeChange scroll toggle touchMove waiting wheel".split(
        " "
      );
  pr.push("scrollEnd");
  function Rt(e, t) {
    To.set(e, t), Da(t, [e]);
  }
  var _o = new WeakMap();
  function St(e, t) {
    if (typeof e == "object" && e !== null) {
      var a = _o.get(e);
      return a !== void 0
        ? a
        : ((t = { value: e, source: t, stack: Vc(t) }), _o.set(e, t), t);
    }
    return { value: e, source: t, stack: Vc(t) };
  }
  var Nt = [],
    ml = 0,
    xr = 0;
  function xs() {
    for (var e = ml, t = (xr = ml = 0); t < e; ) {
      var a = Nt[t];
      Nt[t++] = null;
      var l = Nt[t];
      Nt[t++] = null;
      var n = Nt[t];
      Nt[t++] = null;
      var i = Nt[t];
      if (((Nt[t++] = null), l !== null && n !== null)) {
        var d = l.pending;
        d === null ? (n.next = n) : ((n.next = d.next), (d.next = n)),
          (l.pending = n);
      }
      i !== 0 && Ao(a, n, i);
    }
  }
  function gs(e, t, a, l) {
    (Nt[ml++] = e),
      (Nt[ml++] = t),
      (Nt[ml++] = a),
      (Nt[ml++] = l),
      (xr |= l),
      (e.lanes |= l),
      (e = e.alternate),
      e !== null && (e.lanes |= l);
  }
  function gr(e, t, a, l) {
    return gs(e, t, a, l), ys(e);
  }
  function hl(e, t) {
    return gs(e, null, null, t), ys(e);
  }
  function Ao(e, t, a) {
    e.lanes |= a;
    var l = e.alternate;
    l !== null && (l.lanes |= a);
    for (var n = !1, i = e.return; i !== null; )
      (i.childLanes |= a),
        (l = i.alternate),
        l !== null && (l.childLanes |= a),
        i.tag === 22 &&
          ((e = i.stateNode), e === null || e._visibility & 1 || (n = !0)),
        (e = i),
        (i = i.return);
    return e.tag === 3
      ? ((i = e.stateNode),
        n &&
          t !== null &&
          ((n = 31 - ft(a)),
          (e = i.hiddenUpdates),
          (l = e[n]),
          l === null ? (e[n] = [t]) : l.push(t),
          (t.lane = a | 536870912)),
        i)
      : null;
  }
  function ys(e) {
    if (50 < An) throw ((An = 0), (Nu = null), Error(c(185)));
    for (var t = e.return; t !== null; ) (e = t), (t = e.return);
    return e.tag === 3 ? e.stateNode : null;
  }
  var pl = {};
  function $0(e, t, a, l) {
    (this.tag = e),
      (this.key = a),
      (this.sibling =
        this.child =
        this.return =
        this.stateNode =
        this.type =
        this.elementType =
          null),
      (this.index = 0),
      (this.refCleanup = this.ref = null),
      (this.pendingProps = t),
      (this.dependencies =
        this.memoizedState =
        this.updateQueue =
        this.memoizedProps =
          null),
      (this.mode = l),
      (this.subtreeFlags = this.flags = 0),
      (this.deletions = null),
      (this.childLanes = this.lanes = 0),
      (this.alternate = null);
  }
  function mt(e, t, a, l) {
    return new $0(e, t, a, l);
  }
  function yr(e) {
    return (e = e.prototype), !(!e || !e.isReactComponent);
  }
  function Xt(e, t) {
    var a = e.alternate;
    return (
      a === null
        ? ((a = mt(e.tag, t, e.key, e.mode)),
          (a.elementType = e.elementType),
          (a.type = e.type),
          (a.stateNode = e.stateNode),
          (a.alternate = e),
          (e.alternate = a))
        : ((a.pendingProps = t),
          (a.type = e.type),
          (a.flags = 0),
          (a.subtreeFlags = 0),
          (a.deletions = null)),
      (a.flags = e.flags & 65011712),
      (a.childLanes = e.childLanes),
      (a.lanes = e.lanes),
      (a.child = e.child),
      (a.memoizedProps = e.memoizedProps),
      (a.memoizedState = e.memoizedState),
      (a.updateQueue = e.updateQueue),
      (t = e.dependencies),
      (a.dependencies =
        t === null ? null : { lanes: t.lanes, firstContext: t.firstContext }),
      (a.sibling = e.sibling),
      (a.index = e.index),
      (a.ref = e.ref),
      (a.refCleanup = e.refCleanup),
      a
    );
  }
  function Ro(e, t) {
    e.flags &= 65011714;
    var a = e.alternate;
    return (
      a === null
        ? ((e.childLanes = 0),
          (e.lanes = t),
          (e.child = null),
          (e.subtreeFlags = 0),
          (e.memoizedProps = null),
          (e.memoizedState = null),
          (e.updateQueue = null),
          (e.dependencies = null),
          (e.stateNode = null))
        : ((e.childLanes = a.childLanes),
          (e.lanes = a.lanes),
          (e.child = a.child),
          (e.subtreeFlags = 0),
          (e.deletions = null),
          (e.memoizedProps = a.memoizedProps),
          (e.memoizedState = a.memoizedState),
          (e.updateQueue = a.updateQueue),
          (e.type = a.type),
          (t = a.dependencies),
          (e.dependencies =
            t === null
              ? null
              : { lanes: t.lanes, firstContext: t.firstContext })),
      e
    );
  }
  function bs(e, t, a, l, n, i) {
    var d = 0;
    if (((l = e), typeof e == "function")) yr(e) && (d = 1);
    else if (typeof e == "string")
      d = Wp(e, a, P.current)
        ? 26
        : e === "html" || e === "head" || e === "body"
        ? 27
        : 5;
    else
      e: switch (e) {
        case ce:
          return (e = mt(31, a, t, n)), (e.elementType = ce), (e.lanes = i), e;
        case w:
          return La(a.children, n, i, t);
        case C:
          (d = 8), (n |= 24);
          break;
        case D:
          return (
            (e = mt(12, a, t, n | 2)), (e.elementType = D), (e.lanes = i), e
          );
        case Q:
          return (e = mt(13, a, t, n)), (e.elementType = Q), (e.lanes = i), e;
        case W:
          return (e = mt(19, a, t, n)), (e.elementType = W), (e.lanes = i), e;
        default:
          if (typeof e == "object" && e !== null)
            switch (e.$$typeof) {
              case Y:
              case U:
                d = 10;
                break e;
              case Z:
                d = 9;
                break e;
              case q:
                d = 11;
                break e;
              case re:
                d = 14;
                break e;
              case ue:
                (d = 16), (l = null);
                break e;
            }
          (d = 29),
            (a = Error(c(130, e === null ? "null" : typeof e, ""))),
            (l = null);
      }
    return (
      (t = mt(d, a, t, n)), (t.elementType = e), (t.type = l), (t.lanes = i), t
    );
  }
  function La(e, t, a, l) {
    return (e = mt(7, e, l, t)), (e.lanes = a), e;
  }
  function br(e, t, a) {
    return (e = mt(6, e, null, t)), (e.lanes = a), e;
  }
  function vr(e, t, a) {
    return (
      (t = mt(4, e.children !== null ? e.children : [], e.key, t)),
      (t.lanes = a),
      (t.stateNode = {
        containerInfo: e.containerInfo,
        pendingChildren: null,
        implementation: e.implementation,
      }),
      t
    );
  }
  var xl = [],
    gl = 0,
    vs = null,
    js = 0,
    wt = [],
    Et = 0,
    Ba = null,
    Qt = 1,
    Zt = "";
  function Ha(e, t) {
    (xl[gl++] = js), (xl[gl++] = vs), (vs = e), (js = t);
  }
  function Co(e, t, a) {
    (wt[Et++] = Qt), (wt[Et++] = Zt), (wt[Et++] = Ba), (Ba = e);
    var l = Qt;
    e = Zt;
    var n = 32 - ft(l) - 1;
    (l &= ~(1 << n)), (a += 1);
    var i = 32 - ft(t) + n;
    if (30 < i) {
      var d = n - (n % 5);
      (i = (l & ((1 << d) - 1)).toString(32)),
        (l >>= d),
        (n -= d),
        (Qt = (1 << (32 - ft(t) + n)) | (a << n) | l),
        (Zt = i + e);
    } else (Qt = (1 << i) | (a << n) | l), (Zt = e);
  }
  function jr(e) {
    e.return !== null && (Ha(e, 1), Co(e, 1, 0));
  }
  function Sr(e) {
    for (; e === vs; )
      (vs = xl[--gl]), (xl[gl] = null), (js = xl[--gl]), (xl[gl] = null);
    for (; e === Ba; )
      (Ba = wt[--Et]),
        (wt[Et] = null),
        (Zt = wt[--Et]),
        (wt[Et] = null),
        (Qt = wt[--Et]),
        (wt[Et] = null);
  }
  var at = null,
    ke = null,
    Se = !1,
    qa = null,
    zt = !1,
    Nr = Error(c(519));
  function Ya(e) {
    var t = Error(c(418, ""));
    throw (sn(St(t, e)), Nr);
  }
  function Oo(e) {
    var t = e.stateNode,
      a = e.type,
      l = e.memoizedProps;
    switch (((t[We] = e), (t[lt] = l), a)) {
      case "dialog":
        ge("cancel", t), ge("close", t);
        break;
      case "iframe":
      case "object":
      case "embed":
        ge("load", t);
        break;
      case "video":
      case "audio":
        for (a = 0; a < Cn.length; a++) ge(Cn[a], t);
        break;
      case "source":
        ge("error", t);
        break;
      case "img":
      case "image":
      case "link":
        ge("error", t), ge("load", t);
        break;
      case "details":
        ge("toggle", t);
        break;
      case "input":
        ge("invalid", t),
          Qc(
            t,
            l.value,
            l.defaultValue,
            l.checked,
            l.defaultChecked,
            l.type,
            l.name,
            !0
          ),
          rs(t);
        break;
      case "select":
        ge("invalid", t);
        break;
      case "textarea":
        ge("invalid", t), Kc(t, l.value, l.defaultValue, l.children), rs(t);
    }
    (a = l.children),
      (typeof a != "string" && typeof a != "number" && typeof a != "bigint") ||
      t.textContent === "" + a ||
      l.suppressHydrationWarning === !0 ||
      $d(t.textContent, a)
        ? (l.popover != null && (ge("beforetoggle", t), ge("toggle", t)),
          l.onScroll != null && ge("scroll", t),
          l.onScrollEnd != null && ge("scrollend", t),
          l.onClick != null && (t.onclick = ti),
          (t = !0))
        : (t = !1),
      t || Ya(e);
  }
  function Mo(e) {
    for (at = e.return; at; )
      switch (at.tag) {
        case 5:
        case 13:
          zt = !1;
          return;
        case 27:
        case 3:
          zt = !0;
          return;
        default:
          at = at.return;
      }
  }
  function ln(e) {
    if (e !== at) return !1;
    if (!Se) return Mo(e), (Se = !0), !1;
    var t = e.tag,
      a;
    if (
      ((a = t !== 3 && t !== 27) &&
        ((a = t === 5) &&
          ((a = e.type),
          (a =
            !(a !== "form" && a !== "button") || Hu(e.type, e.memoizedProps))),
        (a = !a)),
      a && ke && Ya(e),
      Mo(e),
      t === 13)
    ) {
      if (((e = e.memoizedState), (e = e !== null ? e.dehydrated : null), !e))
        throw Error(c(317));
      e: {
        for (e = e.nextSibling, t = 0; e; ) {
          if (e.nodeType === 8)
            if (((a = e.data), a === "/$")) {
              if (t === 0) {
                ke = Ot(e.nextSibling);
                break e;
              }
              t--;
            } else (a !== "$" && a !== "$!" && a !== "$?") || t++;
          e = e.nextSibling;
        }
        ke = null;
      }
    } else
      t === 27
        ? ((t = ke), Ea(e.type) ? ((e = Gu), (Gu = null), (ke = e)) : (ke = t))
        : (ke = at ? Ot(e.stateNode.nextSibling) : null);
    return !0;
  }
  function nn() {
    (ke = at = null), (Se = !1);
  }
  function Do() {
    var e = qa;
    return (
      e !== null &&
        (rt === null ? (rt = e) : rt.push.apply(rt, e), (qa = null)),
      e
    );
  }
  function sn(e) {
    qa === null ? (qa = [e]) : qa.push(e);
  }
  var wr = M(null),
    Va = null,
    Kt = null;
  function oa(e, t, a) {
    K(wr, t._currentValue), (t._currentValue = a);
  }
  function Jt(e) {
    (e._currentValue = wr.current), J(wr);
  }
  function Er(e, t, a) {
    for (; e !== null; ) {
      var l = e.alternate;
      if (
        ((e.childLanes & t) !== t
          ? ((e.childLanes |= t), l !== null && (l.childLanes |= t))
          : l !== null && (l.childLanes & t) !== t && (l.childLanes |= t),
        e === a)
      )
        break;
      e = e.return;
    }
  }
  function Tr(e, t, a, l) {
    var n = e.child;
    for (n !== null && (n.return = e); n !== null; ) {
      var i = n.dependencies;
      if (i !== null) {
        var d = n.child;
        i = i.firstContext;
        e: for (; i !== null; ) {
          var h = i;
          i = n;
          for (var j = 0; j < t.length; j++)
            if (h.context === t[j]) {
              (i.lanes |= a),
                (h = i.alternate),
                h !== null && (h.lanes |= a),
                Er(i.return, a, e),
                l || (d = null);
              break e;
            }
          i = h.next;
        }
      } else if (n.tag === 18) {
        if (((d = n.return), d === null)) throw Error(c(341));
        (d.lanes |= a),
          (i = d.alternate),
          i !== null && (i.lanes |= a),
          Er(d, a, e),
          (d = null);
      } else d = n.child;
      if (d !== null) d.return = n;
      else
        for (d = n; d !== null; ) {
          if (d === e) {
            d = null;
            break;
          }
          if (((n = d.sibling), n !== null)) {
            (n.return = d.return), (d = n);
            break;
          }
          d = d.return;
        }
      n = d;
    }
  }
  function rn(e, t, a, l) {
    e = null;
    for (var n = t, i = !1; n !== null; ) {
      if (!i) {
        if ((n.flags & 524288) !== 0) i = !0;
        else if ((n.flags & 262144) !== 0) break;
      }
      if (n.tag === 10) {
        var d = n.alternate;
        if (d === null) throw Error(c(387));
        if (((d = d.memoizedProps), d !== null)) {
          var h = n.type;
          dt(n.pendingProps.value, d.value) ||
            (e !== null ? e.push(h) : (e = [h]));
        }
      } else if (n === tt.current) {
        if (((d = n.alternate), d === null)) throw Error(c(387));
        d.memoizedState.memoizedState !== n.memoizedState.memoizedState &&
          (e !== null ? e.push(kn) : (e = [kn]));
      }
      n = n.return;
    }
    e !== null && Tr(t, e, a, l), (t.flags |= 262144);
  }
  function Ss(e) {
    for (e = e.firstContext; e !== null; ) {
      if (!dt(e.context._currentValue, e.memoizedValue)) return !0;
      e = e.next;
    }
    return !1;
  }
  function Ga(e) {
    (Va = e),
      (Kt = null),
      (e = e.dependencies),
      e !== null && (e.firstContext = null);
  }
  function Pe(e) {
    return zo(Va, e);
  }
  function Ns(e, t) {
    return Va === null && Ga(e), zo(e, t);
  }
  function zo(e, t) {
    var a = t._currentValue;
    if (((t = { context: t, memoizedValue: a, next: null }), Kt === null)) {
      if (e === null) throw Error(c(308));
      (Kt = t),
        (e.dependencies = { lanes: 0, firstContext: t }),
        (e.flags |= 524288);
    } else Kt = Kt.next = t;
    return a;
  }
  var F0 =
      typeof AbortController < "u"
        ? AbortController
        : function () {
            var e = [],
              t = (this.signal = {
                aborted: !1,
                addEventListener: function (a, l) {
                  e.push(l);
                },
              });
            this.abort = function () {
              (t.aborted = !0),
                e.forEach(function (a) {
                  return a();
                });
            };
          },
    W0 = s.unstable_scheduleCallback,
    P0 = s.unstable_NormalPriority,
    Ve = {
      $$typeof: U,
      Consumer: null,
      Provider: null,
      _currentValue: null,
      _currentValue2: null,
      _threadCount: 0,
    };
  function _r() {
    return { controller: new F0(), data: new Map(), refCount: 0 };
  }
  function un(e) {
    e.refCount--,
      e.refCount === 0 &&
        W0(P0, function () {
          e.controller.abort();
        });
  }
  var cn = null,
    Ar = 0,
    yl = 0,
    bl = null;
  function I0(e, t) {
    if (cn === null) {
      var a = (cn = []);
      (Ar = 0),
        (yl = Cu()),
        (bl = {
          status: "pending",
          value: void 0,
          then: function (l) {
            a.push(l);
          },
        });
    }
    return Ar++, t.then(Uo, Uo), t;
  }
  function Uo() {
    if (--Ar === 0 && cn !== null) {
      bl !== null && (bl.status = "fulfilled");
      var e = cn;
      (cn = null), (yl = 0), (bl = null);
      for (var t = 0; t < e.length; t++) (0, e[t])();
    }
  }
  function ep(e, t) {
    var a = [],
      l = {
        status: "pending",
        value: null,
        reason: null,
        then: function (n) {
          a.push(n);
        },
      };
    return (
      e.then(
        function () {
          (l.status = "fulfilled"), (l.value = t);
          for (var n = 0; n < a.length; n++) (0, a[n])(t);
        },
        function (n) {
          for (l.status = "rejected", l.reason = n, n = 0; n < a.length; n++)
            (0, a[n])(void 0);
        }
      ),
      l
    );
  }
  var ko = S.S;
  S.S = function (e, t) {
    typeof t == "object" &&
      t !== null &&
      typeof t.then == "function" &&
      I0(e, t),
      ko !== null && ko(e, t);
  };
  var Xa = M(null);
  function Rr() {
    var e = Xa.current;
    return e !== null ? e : Ce.pooledCache;
  }
  function ws(e, t) {
    t === null ? K(Xa, Xa.current) : K(Xa, t.pool);
  }
  function Lo() {
    var e = Rr();
    return e === null ? null : { parent: Ve._currentValue, pool: e };
  }
  var on = Error(c(460)),
    Bo = Error(c(474)),
    Es = Error(c(542)),
    Cr = { then: function () {} };
  function Ho(e) {
    return (e = e.status), e === "fulfilled" || e === "rejected";
  }
  function Ts() {}
  function qo(e, t, a) {
    switch (
      ((a = e[a]),
      a === void 0 ? e.push(t) : a !== t && (t.then(Ts, Ts), (t = a)),
      t.status)
    ) {
      case "fulfilled":
        return t.value;
      case "rejected":
        throw ((e = t.reason), Vo(e), e);
      default:
        if (typeof t.status == "string") t.then(Ts, Ts);
        else {
          if (((e = Ce), e !== null && 100 < e.shellSuspendCounter))
            throw Error(c(482));
          (e = t),
            (e.status = "pending"),
            e.then(
              function (l) {
                if (t.status === "pending") {
                  var n = t;
                  (n.status = "fulfilled"), (n.value = l);
                }
              },
              function (l) {
                if (t.status === "pending") {
                  var n = t;
                  (n.status = "rejected"), (n.reason = l);
                }
              }
            );
        }
        switch (t.status) {
          case "fulfilled":
            return t.value;
          case "rejected":
            throw ((e = t.reason), Vo(e), e);
        }
        throw ((fn = t), on);
    }
  }
  var fn = null;
  function Yo() {
    if (fn === null) throw Error(c(459));
    var e = fn;
    return (fn = null), e;
  }
  function Vo(e) {
    if (e === on || e === Es) throw Error(c(483));
  }
  var fa = !1;
  function Or(e) {
    e.updateQueue = {
      baseState: e.memoizedState,
      firstBaseUpdate: null,
      lastBaseUpdate: null,
      shared: { pending: null, lanes: 0, hiddenCallbacks: null },
      callbacks: null,
    };
  }
  function Mr(e, t) {
    (e = e.updateQueue),
      t.updateQueue === e &&
        (t.updateQueue = {
          baseState: e.baseState,
          firstBaseUpdate: e.firstBaseUpdate,
          lastBaseUpdate: e.lastBaseUpdate,
          shared: e.shared,
          callbacks: null,
        });
  }
  function da(e) {
    return { lane: e, tag: 0, payload: null, callback: null, next: null };
  }
  function ma(e, t, a) {
    var l = e.updateQueue;
    if (l === null) return null;
    if (((l = l.shared), (Ne & 2) !== 0)) {
      var n = l.pending;
      return (
        n === null ? (t.next = t) : ((t.next = n.next), (n.next = t)),
        (l.pending = t),
        (t = ys(e)),
        Ao(e, null, a),
        t
      );
    }
    return gs(e, l, t, a), ys(e);
  }
  function dn(e, t, a) {
    if (
      ((t = t.updateQueue), t !== null && ((t = t.shared), (a & 4194048) !== 0))
    ) {
      var l = t.lanes;
      (l &= e.pendingLanes), (a |= l), (t.lanes = a), zc(e, a);
    }
  }
  function Dr(e, t) {
    var a = e.updateQueue,
      l = e.alternate;
    if (l !== null && ((l = l.updateQueue), a === l)) {
      var n = null,
        i = null;
      if (((a = a.firstBaseUpdate), a !== null)) {
        do {
          var d = {
            lane: a.lane,
            tag: a.tag,
            payload: a.payload,
            callback: null,
            next: null,
          };
          i === null ? (n = i = d) : (i = i.next = d), (a = a.next);
        } while (a !== null);
        i === null ? (n = i = t) : (i = i.next = t);
      } else n = i = t;
      (a = {
        baseState: l.baseState,
        firstBaseUpdate: n,
        lastBaseUpdate: i,
        shared: l.shared,
        callbacks: l.callbacks,
      }),
        (e.updateQueue = a);
      return;
    }
    (e = a.lastBaseUpdate),
      e === null ? (a.firstBaseUpdate = t) : (e.next = t),
      (a.lastBaseUpdate = t);
  }
  var zr = !1;
  function mn() {
    if (zr) {
      var e = bl;
      if (e !== null) throw e;
    }
  }
  function hn(e, t, a, l) {
    zr = !1;
    var n = e.updateQueue;
    fa = !1;
    var i = n.firstBaseUpdate,
      d = n.lastBaseUpdate,
      h = n.shared.pending;
    if (h !== null) {
      n.shared.pending = null;
      var j = h,
        O = j.next;
      (j.next = null), d === null ? (i = O) : (d.next = O), (d = j);
      var V = e.alternate;
      V !== null &&
        ((V = V.updateQueue),
        (h = V.lastBaseUpdate),
        h !== d &&
          (h === null ? (V.firstBaseUpdate = O) : (h.next = O),
          (V.lastBaseUpdate = j)));
    }
    if (i !== null) {
      var X = n.baseState;
      (d = 0), (V = O = j = null), (h = i);
      do {
        var z = h.lane & -536870913,
          k = z !== h.lane;
        if (k ? (ye & z) === z : (l & z) === z) {
          z !== 0 && z === yl && (zr = !0),
            V !== null &&
              (V = V.next =
                {
                  lane: 0,
                  tag: h.tag,
                  payload: h.payload,
                  callback: null,
                  next: null,
                });
          e: {
            var ie = e,
              ne = h;
            z = t;
            var _e = a;
            switch (ne.tag) {
              case 1:
                if (((ie = ne.payload), typeof ie == "function")) {
                  X = ie.call(_e, X, z);
                  break e;
                }
                X = ie;
                break e;
              case 3:
                ie.flags = (ie.flags & -65537) | 128;
              case 0:
                if (
                  ((ie = ne.payload),
                  (z = typeof ie == "function" ? ie.call(_e, X, z) : ie),
                  z == null)
                )
                  break e;
                X = v({}, X, z);
                break e;
              case 2:
                fa = !0;
            }
          }
          (z = h.callback),
            z !== null &&
              ((e.flags |= 64),
              k && (e.flags |= 8192),
              (k = n.callbacks),
              k === null ? (n.callbacks = [z]) : k.push(z));
        } else
          (k = {
            lane: z,
            tag: h.tag,
            payload: h.payload,
            callback: h.callback,
            next: null,
          }),
            V === null ? ((O = V = k), (j = X)) : (V = V.next = k),
            (d |= z);
        if (((h = h.next), h === null)) {
          if (((h = n.shared.pending), h === null)) break;
          (k = h),
            (h = k.next),
            (k.next = null),
            (n.lastBaseUpdate = k),
            (n.shared.pending = null);
        }
      } while (!0);
      V === null && (j = X),
        (n.baseState = j),
        (n.firstBaseUpdate = O),
        (n.lastBaseUpdate = V),
        i === null && (n.shared.lanes = 0),
        (ja |= d),
        (e.lanes = d),
        (e.memoizedState = X);
    }
  }
  function Go(e, t) {
    if (typeof e != "function") throw Error(c(191, e));
    e.call(t);
  }
  function Xo(e, t) {
    var a = e.callbacks;
    if (a !== null)
      for (e.callbacks = null, e = 0; e < a.length; e++) Go(a[e], t);
  }
  var vl = M(null),
    _s = M(0);
  function Qo(e, t) {
    (e = ta), K(_s, e), K(vl, t), (ta = e | t.baseLanes);
  }
  function Ur() {
    K(_s, ta), K(vl, vl.current);
  }
  function kr() {
    (ta = _s.current), J(vl), J(_s);
  }
  var ha = 0,
    me = null,
    Ee = null,
    qe = null,
    As = !1,
    jl = !1,
    Qa = !1,
    Rs = 0,
    pn = 0,
    Sl = null,
    tp = 0;
  function Be() {
    throw Error(c(321));
  }
  function Lr(e, t) {
    if (t === null) return !1;
    for (var a = 0; a < t.length && a < e.length; a++)
      if (!dt(e[a], t[a])) return !1;
    return !0;
  }
  function Br(e, t, a, l, n, i) {
    return (
      (ha = i),
      (me = t),
      (t.memoizedState = null),
      (t.updateQueue = null),
      (t.lanes = 0),
      (S.H = e === null || e.memoizedState === null ? Rf : Cf),
      (Qa = !1),
      (i = a(l, n)),
      (Qa = !1),
      jl && (i = Ko(t, a, l, n)),
      Zo(e),
      i
    );
  }
  function Zo(e) {
    S.H = Us;
    var t = Ee !== null && Ee.next !== null;
    if (((ha = 0), (qe = Ee = me = null), (As = !1), (pn = 0), (Sl = null), t))
      throw Error(c(300));
    e === null ||
      Qe ||
      ((e = e.dependencies), e !== null && Ss(e) && (Qe = !0));
  }
  function Ko(e, t, a, l) {
    me = e;
    var n = 0;
    do {
      if ((jl && (Sl = null), (pn = 0), (jl = !1), 25 <= n))
        throw Error(c(301));
      if (((n += 1), (qe = Ee = null), e.updateQueue != null)) {
        var i = e.updateQueue;
        (i.lastEffect = null),
          (i.events = null),
          (i.stores = null),
          i.memoCache != null && (i.memoCache.index = 0);
      }
      (S.H = up), (i = t(a, l));
    } while (jl);
    return i;
  }
  function ap() {
    var e = S.H,
      t = e.useState()[0];
    return (
      (t = typeof t.then == "function" ? xn(t) : t),
      (e = e.useState()[0]),
      (Ee !== null ? Ee.memoizedState : null) !== e && (me.flags |= 1024),
      t
    );
  }
  function Hr() {
    var e = Rs !== 0;
    return (Rs = 0), e;
  }
  function qr(e, t, a) {
    (t.updateQueue = e.updateQueue), (t.flags &= -2053), (e.lanes &= ~a);
  }
  function Yr(e) {
    if (As) {
      for (e = e.memoizedState; e !== null; ) {
        var t = e.queue;
        t !== null && (t.pending = null), (e = e.next);
      }
      As = !1;
    }
    (ha = 0), (qe = Ee = me = null), (jl = !1), (pn = Rs = 0), (Sl = null);
  }
  function st() {
    var e = {
      memoizedState: null,
      baseState: null,
      baseQueue: null,
      queue: null,
      next: null,
    };
    return qe === null ? (me.memoizedState = qe = e) : (qe = qe.next = e), qe;
  }
  function Ye() {
    if (Ee === null) {
      var e = me.alternate;
      e = e !== null ? e.memoizedState : null;
    } else e = Ee.next;
    var t = qe === null ? me.memoizedState : qe.next;
    if (t !== null) (qe = t), (Ee = e);
    else {
      if (e === null)
        throw me.alternate === null ? Error(c(467)) : Error(c(310));
      (Ee = e),
        (e = {
          memoizedState: Ee.memoizedState,
          baseState: Ee.baseState,
          baseQueue: Ee.baseQueue,
          queue: Ee.queue,
          next: null,
        }),
        qe === null ? (me.memoizedState = qe = e) : (qe = qe.next = e);
    }
    return qe;
  }
  function Vr() {
    return { lastEffect: null, events: null, stores: null, memoCache: null };
  }
  function xn(e) {
    var t = pn;
    return (
      (pn += 1),
      Sl === null && (Sl = []),
      (e = qo(Sl, e, t)),
      (t = me),
      (qe === null ? t.memoizedState : qe.next) === null &&
        ((t = t.alternate),
        (S.H = t === null || t.memoizedState === null ? Rf : Cf)),
      e
    );
  }
  function Cs(e) {
    if (e !== null && typeof e == "object") {
      if (typeof e.then == "function") return xn(e);
      if (e.$$typeof === U) return Pe(e);
    }
    throw Error(c(438, String(e)));
  }
  function Gr(e) {
    var t = null,
      a = me.updateQueue;
    if ((a !== null && (t = a.memoCache), t == null)) {
      var l = me.alternate;
      l !== null &&
        ((l = l.updateQueue),
        l !== null &&
          ((l = l.memoCache),
          l != null &&
            (t = {
              data: l.data.map(function (n) {
                return n.slice();
              }),
              index: 0,
            })));
    }
    if (
      (t == null && (t = { data: [], index: 0 }),
      a === null && ((a = Vr()), (me.updateQueue = a)),
      (a.memoCache = t),
      (a = t.data[t.index]),
      a === void 0)
    )
      for (a = t.data[t.index] = Array(e), l = 0; l < e; l++) a[l] = je;
    return t.index++, a;
  }
  function $t(e, t) {
    return typeof t == "function" ? t(e) : t;
  }
  function Os(e) {
    var t = Ye();
    return Xr(t, Ee, e);
  }
  function Xr(e, t, a) {
    var l = e.queue;
    if (l === null) throw Error(c(311));
    l.lastRenderedReducer = a;
    var n = e.baseQueue,
      i = l.pending;
    if (i !== null) {
      if (n !== null) {
        var d = n.next;
        (n.next = i.next), (i.next = d);
      }
      (t.baseQueue = n = i), (l.pending = null);
    }
    if (((i = e.baseState), n === null)) e.memoizedState = i;
    else {
      t = n.next;
      var h = (d = null),
        j = null,
        O = t,
        V = !1;
      do {
        var X = O.lane & -536870913;
        if (X !== O.lane ? (ye & X) === X : (ha & X) === X) {
          var z = O.revertLane;
          if (z === 0)
            j !== null &&
              (j = j.next =
                {
                  lane: 0,
                  revertLane: 0,
                  action: O.action,
                  hasEagerState: O.hasEagerState,
                  eagerState: O.eagerState,
                  next: null,
                }),
              X === yl && (V = !0);
          else if ((ha & z) === z) {
            (O = O.next), z === yl && (V = !0);
            continue;
          } else
            (X = {
              lane: 0,
              revertLane: O.revertLane,
              action: O.action,
              hasEagerState: O.hasEagerState,
              eagerState: O.eagerState,
              next: null,
            }),
              j === null ? ((h = j = X), (d = i)) : (j = j.next = X),
              (me.lanes |= z),
              (ja |= z);
          (X = O.action),
            Qa && a(i, X),
            (i = O.hasEagerState ? O.eagerState : a(i, X));
        } else
          (z = {
            lane: X,
            revertLane: O.revertLane,
            action: O.action,
            hasEagerState: O.hasEagerState,
            eagerState: O.eagerState,
            next: null,
          }),
            j === null ? ((h = j = z), (d = i)) : (j = j.next = z),
            (me.lanes |= X),
            (ja |= X);
        O = O.next;
      } while (O !== null && O !== t);
      if (
        (j === null ? (d = i) : (j.next = h),
        !dt(i, e.memoizedState) && ((Qe = !0), V && ((a = bl), a !== null)))
      )
        throw a;
      (e.memoizedState = i),
        (e.baseState = d),
        (e.baseQueue = j),
        (l.lastRenderedState = i);
    }
    return n === null && (l.lanes = 0), [e.memoizedState, l.dispatch];
  }
  function Qr(e) {
    var t = Ye(),
      a = t.queue;
    if (a === null) throw Error(c(311));
    a.lastRenderedReducer = e;
    var l = a.dispatch,
      n = a.pending,
      i = t.memoizedState;
    if (n !== null) {
      a.pending = null;
      var d = (n = n.next);
      do (i = e(i, d.action)), (d = d.next);
      while (d !== n);
      dt(i, t.memoizedState) || (Qe = !0),
        (t.memoizedState = i),
        t.baseQueue === null && (t.baseState = i),
        (a.lastRenderedState = i);
    }
    return [i, l];
  }
  function Jo(e, t, a) {
    var l = me,
      n = Ye(),
      i = Se;
    if (i) {
      if (a === void 0) throw Error(c(407));
      a = a();
    } else a = t();
    var d = !dt((Ee || n).memoizedState, a);
    d && ((n.memoizedState = a), (Qe = !0)), (n = n.queue);
    var h = Wo.bind(null, l, n, e);
    if (
      (gn(2048, 8, h, [e]),
      n.getSnapshot !== t || d || (qe !== null && qe.memoizedState.tag & 1))
    ) {
      if (
        ((l.flags |= 2048),
        Nl(9, Ms(), Fo.bind(null, l, n, a, t), null),
        Ce === null)
      )
        throw Error(c(349));
      i || (ha & 124) !== 0 || $o(l, t, a);
    }
    return a;
  }
  function $o(e, t, a) {
    (e.flags |= 16384),
      (e = { getSnapshot: t, value: a }),
      (t = me.updateQueue),
      t === null
        ? ((t = Vr()), (me.updateQueue = t), (t.stores = [e]))
        : ((a = t.stores), a === null ? (t.stores = [e]) : a.push(e));
  }
  function Fo(e, t, a, l) {
    (t.value = a), (t.getSnapshot = l), Po(t) && Io(e);
  }
  function Wo(e, t, a) {
    return a(function () {
      Po(t) && Io(e);
    });
  }
  function Po(e) {
    var t = e.getSnapshot;
    e = e.value;
    try {
      var a = t();
      return !dt(e, a);
    } catch {
      return !0;
    }
  }
  function Io(e) {
    var t = hl(e, 2);
    t !== null && yt(t, e, 2);
  }
  function Zr(e) {
    var t = st();
    if (typeof e == "function") {
      var a = e;
      if (((e = a()), Qa)) {
        ra(!0);
        try {
          a();
        } finally {
          ra(!1);
        }
      }
    }
    return (
      (t.memoizedState = t.baseState = e),
      (t.queue = {
        pending: null,
        lanes: 0,
        dispatch: null,
        lastRenderedReducer: $t,
        lastRenderedState: e,
      }),
      t
    );
  }
  function ef(e, t, a, l) {
    return (e.baseState = a), Xr(e, Ee, typeof l == "function" ? l : $t);
  }
  function lp(e, t, a, l, n) {
    if (zs(e)) throw Error(c(485));
    if (((e = t.action), e !== null)) {
      var i = {
        payload: n,
        action: e,
        next: null,
        isTransition: !0,
        status: "pending",
        value: null,
        reason: null,
        listeners: [],
        then: function (d) {
          i.listeners.push(d);
        },
      };
      S.T !== null ? a(!0) : (i.isTransition = !1),
        l(i),
        (a = t.pending),
        a === null
          ? ((i.next = t.pending = i), tf(t, i))
          : ((i.next = a.next), (t.pending = a.next = i));
    }
  }
  function tf(e, t) {
    var a = t.action,
      l = t.payload,
      n = e.state;
    if (t.isTransition) {
      var i = S.T,
        d = {};
      S.T = d;
      try {
        var h = a(n, l),
          j = S.S;
        j !== null && j(d, h), af(e, t, h);
      } catch (O) {
        Kr(e, t, O);
      } finally {
        S.T = i;
      }
    } else
      try {
        (i = a(n, l)), af(e, t, i);
      } catch (O) {
        Kr(e, t, O);
      }
  }
  function af(e, t, a) {
    a !== null && typeof a == "object" && typeof a.then == "function"
      ? a.then(
          function (l) {
            lf(e, t, l);
          },
          function (l) {
            return Kr(e, t, l);
          }
        )
      : lf(e, t, a);
  }
  function lf(e, t, a) {
    (t.status = "fulfilled"),
      (t.value = a),
      nf(t),
      (e.state = a),
      (t = e.pending),
      t !== null &&
        ((a = t.next),
        a === t ? (e.pending = null) : ((a = a.next), (t.next = a), tf(e, a)));
  }
  function Kr(e, t, a) {
    var l = e.pending;
    if (((e.pending = null), l !== null)) {
      l = l.next;
      do (t.status = "rejected"), (t.reason = a), nf(t), (t = t.next);
      while (t !== l);
    }
    e.action = null;
  }
  function nf(e) {
    e = e.listeners;
    for (var t = 0; t < e.length; t++) (0, e[t])();
  }
  function sf(e, t) {
    return t;
  }
  function rf(e, t) {
    if (Se) {
      var a = Ce.formState;
      if (a !== null) {
        e: {
          var l = me;
          if (Se) {
            if (ke) {
              t: {
                for (var n = ke, i = zt; n.nodeType !== 8; ) {
                  if (!i) {
                    n = null;
                    break t;
                  }
                  if (((n = Ot(n.nextSibling)), n === null)) {
                    n = null;
                    break t;
                  }
                }
                (i = n.data), (n = i === "F!" || i === "F" ? n : null);
              }
              if (n) {
                (ke = Ot(n.nextSibling)), (l = n.data === "F!");
                break e;
              }
            }
            Ya(l);
          }
          l = !1;
        }
        l && (t = a[0]);
      }
    }
    return (
      (a = st()),
      (a.memoizedState = a.baseState = t),
      (l = {
        pending: null,
        lanes: 0,
        dispatch: null,
        lastRenderedReducer: sf,
        lastRenderedState: t,
      }),
      (a.queue = l),
      (a = Tf.bind(null, me, l)),
      (l.dispatch = a),
      (l = Zr(!1)),
      (i = Pr.bind(null, me, !1, l.queue)),
      (l = st()),
      (n = { state: t, dispatch: null, action: e, pending: null }),
      (l.queue = n),
      (a = lp.bind(null, me, n, i, a)),
      (n.dispatch = a),
      (l.memoizedState = e),
      [t, a, !1]
    );
  }
  function uf(e) {
    var t = Ye();
    return cf(t, Ee, e);
  }
  function cf(e, t, a) {
    if (
      ((t = Xr(e, t, sf)[0]),
      (e = Os($t)[0]),
      typeof t == "object" && t !== null && typeof t.then == "function")
    )
      try {
        var l = xn(t);
      } catch (d) {
        throw d === on ? Es : d;
      }
    else l = t;
    t = Ye();
    var n = t.queue,
      i = n.dispatch;
    return (
      a !== t.memoizedState &&
        ((me.flags |= 2048), Nl(9, Ms(), np.bind(null, n, a), null)),
      [l, i, e]
    );
  }
  function np(e, t) {
    e.action = t;
  }
  function of(e) {
    var t = Ye(),
      a = Ee;
    if (a !== null) return cf(t, a, e);
    Ye(), (t = t.memoizedState), (a = Ye());
    var l = a.queue.dispatch;
    return (a.memoizedState = e), [t, l, !1];
  }
  function Nl(e, t, a, l) {
    return (
      (e = { tag: e, create: a, deps: l, inst: t, next: null }),
      (t = me.updateQueue),
      t === null && ((t = Vr()), (me.updateQueue = t)),
      (a = t.lastEffect),
      a === null
        ? (t.lastEffect = e.next = e)
        : ((l = a.next), (a.next = e), (e.next = l), (t.lastEffect = e)),
      e
    );
  }
  function Ms() {
    return { destroy: void 0, resource: void 0 };
  }
  function ff() {
    return Ye().memoizedState;
  }
  function Ds(e, t, a, l) {
    var n = st();
    (l = l === void 0 ? null : l),
      (me.flags |= e),
      (n.memoizedState = Nl(1 | t, Ms(), a, l));
  }
  function gn(e, t, a, l) {
    var n = Ye();
    l = l === void 0 ? null : l;
    var i = n.memoizedState.inst;
    Ee !== null && l !== null && Lr(l, Ee.memoizedState.deps)
      ? (n.memoizedState = Nl(t, i, a, l))
      : ((me.flags |= e), (n.memoizedState = Nl(1 | t, i, a, l)));
  }
  function df(e, t) {
    Ds(8390656, 8, e, t);
  }
  function mf(e, t) {
    gn(2048, 8, e, t);
  }
  function hf(e, t) {
    return gn(4, 2, e, t);
  }
  function pf(e, t) {
    return gn(4, 4, e, t);
  }
  function xf(e, t) {
    if (typeof t == "function") {
      e = e();
      var a = t(e);
      return function () {
        typeof a == "function" ? a() : t(null);
      };
    }
    if (t != null)
      return (
        (e = e()),
        (t.current = e),
        function () {
          t.current = null;
        }
      );
  }
  function gf(e, t, a) {
    (a = a != null ? a.concat([e]) : null), gn(4, 4, xf.bind(null, t, e), a);
  }
  function Jr() {}
  function yf(e, t) {
    var a = Ye();
    t = t === void 0 ? null : t;
    var l = a.memoizedState;
    return t !== null && Lr(t, l[1]) ? l[0] : ((a.memoizedState = [e, t]), e);
  }
  function bf(e, t) {
    var a = Ye();
    t = t === void 0 ? null : t;
    var l = a.memoizedState;
    if (t !== null && Lr(t, l[1])) return l[0];
    if (((l = e()), Qa)) {
      ra(!0);
      try {
        e();
      } finally {
        ra(!1);
      }
    }
    return (a.memoizedState = [l, t]), l;
  }
  function $r(e, t, a) {
    return a === void 0 || (ha & 1073741824) !== 0
      ? (e.memoizedState = t)
      : ((e.memoizedState = a), (e = Sd()), (me.lanes |= e), (ja |= e), a);
  }
  function vf(e, t, a, l) {
    return dt(a, t)
      ? a
      : vl.current !== null
      ? ((e = $r(e, a, l)), dt(e, t) || (Qe = !0), e)
      : (ha & 42) === 0
      ? ((Qe = !0), (e.memoizedState = a))
      : ((e = Sd()), (me.lanes |= e), (ja |= e), t);
  }
  function jf(e, t, a, l, n) {
    var i = H.p;
    H.p = i !== 0 && 8 > i ? i : 8;
    var d = S.T,
      h = {};
    (S.T = h), Pr(e, !1, t, a);
    try {
      var j = n(),
        O = S.S;
      if (
        (O !== null && O(h, j),
        j !== null && typeof j == "object" && typeof j.then == "function")
      ) {
        var V = ep(j, l);
        yn(e, t, V, gt(e));
      } else yn(e, t, l, gt(e));
    } catch (X) {
      yn(e, t, { then: function () {}, status: "rejected", reason: X }, gt());
    } finally {
      (H.p = i), (S.T = d);
    }
  }
  function sp() {}
  function Fr(e, t, a, l) {
    if (e.tag !== 5) throw Error(c(476));
    var n = Sf(e).queue;
    jf(
      e,
      n,
      t,
      $,
      a === null
        ? sp
        : function () {
            return Nf(e), a(l);
          }
    );
  }
  function Sf(e) {
    var t = e.memoizedState;
    if (t !== null) return t;
    t = {
      memoizedState: $,
      baseState: $,
      baseQueue: null,
      queue: {
        pending: null,
        lanes: 0,
        dispatch: null,
        lastRenderedReducer: $t,
        lastRenderedState: $,
      },
      next: null,
    };
    var a = {};
    return (
      (t.next = {
        memoizedState: a,
        baseState: a,
        baseQueue: null,
        queue: {
          pending: null,
          lanes: 0,
          dispatch: null,
          lastRenderedReducer: $t,
          lastRenderedState: a,
        },
        next: null,
      }),
      (e.memoizedState = t),
      (e = e.alternate),
      e !== null && (e.memoizedState = t),
      t
    );
  }
  function Nf(e) {
    var t = Sf(e).next.queue;
    yn(e, t, {}, gt());
  }
  function Wr() {
    return Pe(kn);
  }
  function wf() {
    return Ye().memoizedState;
  }
  function Ef() {
    return Ye().memoizedState;
  }
  function ip(e) {
    for (var t = e.return; t !== null; ) {
      switch (t.tag) {
        case 24:
        case 3:
          var a = gt();
          e = da(a);
          var l = ma(t, e, a);
          l !== null && (yt(l, t, a), dn(l, t, a)),
            (t = { cache: _r() }),
            (e.payload = t);
          return;
      }
      t = t.return;
    }
  }
  function rp(e, t, a) {
    var l = gt();
    (a = {
      lane: l,
      revertLane: 0,
      action: a,
      hasEagerState: !1,
      eagerState: null,
      next: null,
    }),
      zs(e)
        ? _f(t, a)
        : ((a = gr(e, t, a, l)), a !== null && (yt(a, e, l), Af(a, t, l)));
  }
  function Tf(e, t, a) {
    var l = gt();
    yn(e, t, a, l);
  }
  function yn(e, t, a, l) {
    var n = {
      lane: l,
      revertLane: 0,
      action: a,
      hasEagerState: !1,
      eagerState: null,
      next: null,
    };
    if (zs(e)) _f(t, n);
    else {
      var i = e.alternate;
      if (
        e.lanes === 0 &&
        (i === null || i.lanes === 0) &&
        ((i = t.lastRenderedReducer), i !== null)
      )
        try {
          var d = t.lastRenderedState,
            h = i(d, a);
          if (((n.hasEagerState = !0), (n.eagerState = h), dt(h, d)))
            return gs(e, t, n, 0), Ce === null && xs(), !1;
        } catch {
        } finally {
        }
      if (((a = gr(e, t, n, l)), a !== null))
        return yt(a, e, l), Af(a, t, l), !0;
    }
    return !1;
  }
  function Pr(e, t, a, l) {
    if (
      ((l = {
        lane: 2,
        revertLane: Cu(),
        action: l,
        hasEagerState: !1,
        eagerState: null,
        next: null,
      }),
      zs(e))
    ) {
      if (t) throw Error(c(479));
    } else (t = gr(e, a, l, 2)), t !== null && yt(t, e, 2);
  }
  function zs(e) {
    var t = e.alternate;
    return e === me || (t !== null && t === me);
  }
  function _f(e, t) {
    jl = As = !0;
    var a = e.pending;
    a === null ? (t.next = t) : ((t.next = a.next), (a.next = t)),
      (e.pending = t);
  }
  function Af(e, t, a) {
    if ((a & 4194048) !== 0) {
      var l = t.lanes;
      (l &= e.pendingLanes), (a |= l), (t.lanes = a), zc(e, a);
    }
  }
  var Us = {
      readContext: Pe,
      use: Cs,
      useCallback: Be,
      useContext: Be,
      useEffect: Be,
      useImperativeHandle: Be,
      useLayoutEffect: Be,
      useInsertionEffect: Be,
      useMemo: Be,
      useReducer: Be,
      useRef: Be,
      useState: Be,
      useDebugValue: Be,
      useDeferredValue: Be,
      useTransition: Be,
      useSyncExternalStore: Be,
      useId: Be,
      useHostTransitionStatus: Be,
      useFormState: Be,
      useActionState: Be,
      useOptimistic: Be,
      useMemoCache: Be,
      useCacheRefresh: Be,
    },
    Rf = {
      readContext: Pe,
      use: Cs,
      useCallback: function (e, t) {
        return (st().memoizedState = [e, t === void 0 ? null : t]), e;
      },
      useContext: Pe,
      useEffect: df,
      useImperativeHandle: function (e, t, a) {
        (a = a != null ? a.concat([e]) : null),
          Ds(4194308, 4, xf.bind(null, t, e), a);
      },
      useLayoutEffect: function (e, t) {
        return Ds(4194308, 4, e, t);
      },
      useInsertionEffect: function (e, t) {
        Ds(4, 2, e, t);
      },
      useMemo: function (e, t) {
        var a = st();
        t = t === void 0 ? null : t;
        var l = e();
        if (Qa) {
          ra(!0);
          try {
            e();
          } finally {
            ra(!1);
          }
        }
        return (a.memoizedState = [l, t]), l;
      },
      useReducer: function (e, t, a) {
        var l = st();
        if (a !== void 0) {
          var n = a(t);
          if (Qa) {
            ra(!0);
            try {
              a(t);
            } finally {
              ra(!1);
            }
          }
        } else n = t;
        return (
          (l.memoizedState = l.baseState = n),
          (e = {
            pending: null,
            lanes: 0,
            dispatch: null,
            lastRenderedReducer: e,
            lastRenderedState: n,
          }),
          (l.queue = e),
          (e = e.dispatch = rp.bind(null, me, e)),
          [l.memoizedState, e]
        );
      },
      useRef: function (e) {
        var t = st();
        return (e = { current: e }), (t.memoizedState = e);
      },
      useState: function (e) {
        e = Zr(e);
        var t = e.queue,
          a = Tf.bind(null, me, t);
        return (t.dispatch = a), [e.memoizedState, a];
      },
      useDebugValue: Jr,
      useDeferredValue: function (e, t) {
        var a = st();
        return $r(a, e, t);
      },
      useTransition: function () {
        var e = Zr(!1);
        return (
          (e = jf.bind(null, me, e.queue, !0, !1)),
          (st().memoizedState = e),
          [!1, e]
        );
      },
      useSyncExternalStore: function (e, t, a) {
        var l = me,
          n = st();
        if (Se) {
          if (a === void 0) throw Error(c(407));
          a = a();
        } else {
          if (((a = t()), Ce === null)) throw Error(c(349));
          (ye & 124) !== 0 || $o(l, t, a);
        }
        n.memoizedState = a;
        var i = { value: a, getSnapshot: t };
        return (
          (n.queue = i),
          df(Wo.bind(null, l, i, e), [e]),
          (l.flags |= 2048),
          Nl(9, Ms(), Fo.bind(null, l, i, a, t), null),
          a
        );
      },
      useId: function () {
        var e = st(),
          t = Ce.identifierPrefix;
        if (Se) {
          var a = Zt,
            l = Qt;
          (a = (l & ~(1 << (32 - ft(l) - 1))).toString(32) + a),
            (t = "«" + t + "R" + a),
            (a = Rs++),
            0 < a && (t += "H" + a.toString(32)),
            (t += "»");
        } else (a = tp++), (t = "«" + t + "r" + a.toString(32) + "»");
        return (e.memoizedState = t);
      },
      useHostTransitionStatus: Wr,
      useFormState: rf,
      useActionState: rf,
      useOptimistic: function (e) {
        var t = st();
        t.memoizedState = t.baseState = e;
        var a = {
          pending: null,
          lanes: 0,
          dispatch: null,
          lastRenderedReducer: null,
          lastRenderedState: null,
        };
        return (
          (t.queue = a),
          (t = Pr.bind(null, me, !0, a)),
          (a.dispatch = t),
          [e, t]
        );
      },
      useMemoCache: Gr,
      useCacheRefresh: function () {
        return (st().memoizedState = ip.bind(null, me));
      },
    },
    Cf = {
      readContext: Pe,
      use: Cs,
      useCallback: yf,
      useContext: Pe,
      useEffect: mf,
      useImperativeHandle: gf,
      useInsertionEffect: hf,
      useLayoutEffect: pf,
      useMemo: bf,
      useReducer: Os,
      useRef: ff,
      useState: function () {
        return Os($t);
      },
      useDebugValue: Jr,
      useDeferredValue: function (e, t) {
        var a = Ye();
        return vf(a, Ee.memoizedState, e, t);
      },
      useTransition: function () {
        var e = Os($t)[0],
          t = Ye().memoizedState;
        return [typeof e == "boolean" ? e : xn(e), t];
      },
      useSyncExternalStore: Jo,
      useId: wf,
      useHostTransitionStatus: Wr,
      useFormState: uf,
      useActionState: uf,
      useOptimistic: function (e, t) {
        var a = Ye();
        return ef(a, Ee, e, t);
      },
      useMemoCache: Gr,
      useCacheRefresh: Ef,
    },
    up = {
      readContext: Pe,
      use: Cs,
      useCallback: yf,
      useContext: Pe,
      useEffect: mf,
      useImperativeHandle: gf,
      useInsertionEffect: hf,
      useLayoutEffect: pf,
      useMemo: bf,
      useReducer: Qr,
      useRef: ff,
      useState: function () {
        return Qr($t);
      },
      useDebugValue: Jr,
      useDeferredValue: function (e, t) {
        var a = Ye();
        return Ee === null ? $r(a, e, t) : vf(a, Ee.memoizedState, e, t);
      },
      useTransition: function () {
        var e = Qr($t)[0],
          t = Ye().memoizedState;
        return [typeof e == "boolean" ? e : xn(e), t];
      },
      useSyncExternalStore: Jo,
      useId: wf,
      useHostTransitionStatus: Wr,
      useFormState: of,
      useActionState: of,
      useOptimistic: function (e, t) {
        var a = Ye();
        return Ee !== null
          ? ef(a, Ee, e, t)
          : ((a.baseState = e), [e, a.queue.dispatch]);
      },
      useMemoCache: Gr,
      useCacheRefresh: Ef,
    },
    wl = null,
    bn = 0;
  function ks(e) {
    var t = bn;
    return (bn += 1), wl === null && (wl = []), qo(wl, e, t);
  }
  function vn(e, t) {
    (t = t.props.ref), (e.ref = t !== void 0 ? t : null);
  }
  function Ls(e, t) {
    throw t.$$typeof === R
      ? Error(c(525))
      : ((e = Object.prototype.toString.call(t)),
        Error(
          c(
            31,
            e === "[object Object]"
              ? "object with keys {" + Object.keys(t).join(", ") + "}"
              : e
          )
        ));
  }
  function Of(e) {
    var t = e._init;
    return t(e._payload);
  }
  function Mf(e) {
    function t(_, E) {
      if (e) {
        var A = _.deletions;
        A === null ? ((_.deletions = [E]), (_.flags |= 16)) : A.push(E);
      }
    }
    function a(_, E) {
      if (!e) return null;
      for (; E !== null; ) t(_, E), (E = E.sibling);
      return null;
    }
    function l(_) {
      for (var E = new Map(); _ !== null; )
        _.key !== null ? E.set(_.key, _) : E.set(_.index, _), (_ = _.sibling);
      return E;
    }
    function n(_, E) {
      return (_ = Xt(_, E)), (_.index = 0), (_.sibling = null), _;
    }
    function i(_, E, A) {
      return (
        (_.index = A),
        e
          ? ((A = _.alternate),
            A !== null
              ? ((A = A.index), A < E ? ((_.flags |= 67108866), E) : A)
              : ((_.flags |= 67108866), E))
          : ((_.flags |= 1048576), E)
      );
    }
    function d(_) {
      return e && _.alternate === null && (_.flags |= 67108866), _;
    }
    function h(_, E, A, G) {
      return E === null || E.tag !== 6
        ? ((E = br(A, _.mode, G)), (E.return = _), E)
        : ((E = n(E, A)), (E.return = _), E);
    }
    function j(_, E, A, G) {
      var I = A.type;
      return I === w
        ? V(_, E, A.props.children, G, A.key)
        : E !== null &&
          (E.elementType === I ||
            (typeof I == "object" &&
              I !== null &&
              I.$$typeof === ue &&
              Of(I) === E.type))
        ? ((E = n(E, A.props)), vn(E, A), (E.return = _), E)
        : ((E = bs(A.type, A.key, A.props, null, _.mode, G)),
          vn(E, A),
          (E.return = _),
          E);
    }
    function O(_, E, A, G) {
      return E === null ||
        E.tag !== 4 ||
        E.stateNode.containerInfo !== A.containerInfo ||
        E.stateNode.implementation !== A.implementation
        ? ((E = vr(A, _.mode, G)), (E.return = _), E)
        : ((E = n(E, A.children || [])), (E.return = _), E);
    }
    function V(_, E, A, G, I) {
      return E === null || E.tag !== 7
        ? ((E = La(A, _.mode, G, I)), (E.return = _), E)
        : ((E = n(E, A)), (E.return = _), E);
    }
    function X(_, E, A) {
      if (
        (typeof E == "string" && E !== "") ||
        typeof E == "number" ||
        typeof E == "bigint"
      )
        return (E = br("" + E, _.mode, A)), (E.return = _), E;
      if (typeof E == "object" && E !== null) {
        switch (E.$$typeof) {
          case N:
            return (
              (A = bs(E.type, E.key, E.props, null, _.mode, A)),
              vn(A, E),
              (A.return = _),
              A
            );
          case L:
            return (E = vr(E, _.mode, A)), (E.return = _), E;
          case ue:
            var G = E._init;
            return (E = G(E._payload)), X(_, E, A);
        }
        if (pe(E) || Me(E))
          return (E = La(E, _.mode, A, null)), (E.return = _), E;
        if (typeof E.then == "function") return X(_, ks(E), A);
        if (E.$$typeof === U) return X(_, Ns(_, E), A);
        Ls(_, E);
      }
      return null;
    }
    function z(_, E, A, G) {
      var I = E !== null ? E.key : null;
      if (
        (typeof A == "string" && A !== "") ||
        typeof A == "number" ||
        typeof A == "bigint"
      )
        return I !== null ? null : h(_, E, "" + A, G);
      if (typeof A == "object" && A !== null) {
        switch (A.$$typeof) {
          case N:
            return A.key === I ? j(_, E, A, G) : null;
          case L:
            return A.key === I ? O(_, E, A, G) : null;
          case ue:
            return (I = A._init), (A = I(A._payload)), z(_, E, A, G);
        }
        if (pe(A) || Me(A)) return I !== null ? null : V(_, E, A, G, null);
        if (typeof A.then == "function") return z(_, E, ks(A), G);
        if (A.$$typeof === U) return z(_, E, Ns(_, A), G);
        Ls(_, A);
      }
      return null;
    }
    function k(_, E, A, G, I) {
      if (
        (typeof G == "string" && G !== "") ||
        typeof G == "number" ||
        typeof G == "bigint"
      )
        return (_ = _.get(A) || null), h(E, _, "" + G, I);
      if (typeof G == "object" && G !== null) {
        switch (G.$$typeof) {
          case N:
            return (
              (_ = _.get(G.key === null ? A : G.key) || null), j(E, _, G, I)
            );
          case L:
            return (
              (_ = _.get(G.key === null ? A : G.key) || null), O(E, _, G, I)
            );
          case ue:
            var he = G._init;
            return (G = he(G._payload)), k(_, E, A, G, I);
        }
        if (pe(G) || Me(G)) return (_ = _.get(A) || null), V(E, _, G, I, null);
        if (typeof G.then == "function") return k(_, E, A, ks(G), I);
        if (G.$$typeof === U) return k(_, E, A, Ns(E, G), I);
        Ls(E, G);
      }
      return null;
    }
    function ie(_, E, A, G) {
      for (
        var I = null, he = null, ae = E, se = (E = 0), Ke = null;
        ae !== null && se < A.length;
        se++
      ) {
        ae.index > se ? ((Ke = ae), (ae = null)) : (Ke = ae.sibling);
        var ve = z(_, ae, A[se], G);
        if (ve === null) {
          ae === null && (ae = Ke);
          break;
        }
        e && ae && ve.alternate === null && t(_, ae),
          (E = i(ve, E, se)),
          he === null ? (I = ve) : (he.sibling = ve),
          (he = ve),
          (ae = Ke);
      }
      if (se === A.length) return a(_, ae), Se && Ha(_, se), I;
      if (ae === null) {
        for (; se < A.length; se++)
          (ae = X(_, A[se], G)),
            ae !== null &&
              ((E = i(ae, E, se)),
              he === null ? (I = ae) : (he.sibling = ae),
              (he = ae));
        return Se && Ha(_, se), I;
      }
      for (ae = l(ae); se < A.length; se++)
        (Ke = k(ae, _, se, A[se], G)),
          Ke !== null &&
            (e &&
              Ke.alternate !== null &&
              ae.delete(Ke.key === null ? se : Ke.key),
            (E = i(Ke, E, se)),
            he === null ? (I = Ke) : (he.sibling = Ke),
            (he = Ke));
      return (
        e &&
          ae.forEach(function (Ca) {
            return t(_, Ca);
          }),
        Se && Ha(_, se),
        I
      );
    }
    function ne(_, E, A, G) {
      if (A == null) throw Error(c(151));
      for (
        var I = null, he = null, ae = E, se = (E = 0), Ke = null, ve = A.next();
        ae !== null && !ve.done;
        se++, ve = A.next()
      ) {
        ae.index > se ? ((Ke = ae), (ae = null)) : (Ke = ae.sibling);
        var Ca = z(_, ae, ve.value, G);
        if (Ca === null) {
          ae === null && (ae = Ke);
          break;
        }
        e && ae && Ca.alternate === null && t(_, ae),
          (E = i(Ca, E, se)),
          he === null ? (I = Ca) : (he.sibling = Ca),
          (he = Ca),
          (ae = Ke);
      }
      if (ve.done) return a(_, ae), Se && Ha(_, se), I;
      if (ae === null) {
        for (; !ve.done; se++, ve = A.next())
          (ve = X(_, ve.value, G)),
            ve !== null &&
              ((E = i(ve, E, se)),
              he === null ? (I = ve) : (he.sibling = ve),
              (he = ve));
        return Se && Ha(_, se), I;
      }
      for (ae = l(ae); !ve.done; se++, ve = A.next())
        (ve = k(ae, _, se, ve.value, G)),
          ve !== null &&
            (e &&
              ve.alternate !== null &&
              ae.delete(ve.key === null ? se : ve.key),
            (E = i(ve, E, se)),
            he === null ? (I = ve) : (he.sibling = ve),
            (he = ve));
      return (
        e &&
          ae.forEach(function (cx) {
            return t(_, cx);
          }),
        Se && Ha(_, se),
        I
      );
    }
    function _e(_, E, A, G) {
      if (
        (typeof A == "object" &&
          A !== null &&
          A.type === w &&
          A.key === null &&
          (A = A.props.children),
        typeof A == "object" && A !== null)
      ) {
        switch (A.$$typeof) {
          case N:
            e: {
              for (var I = A.key; E !== null; ) {
                if (E.key === I) {
                  if (((I = A.type), I === w)) {
                    if (E.tag === 7) {
                      a(_, E.sibling),
                        (G = n(E, A.props.children)),
                        (G.return = _),
                        (_ = G);
                      break e;
                    }
                  } else if (
                    E.elementType === I ||
                    (typeof I == "object" &&
                      I !== null &&
                      I.$$typeof === ue &&
                      Of(I) === E.type)
                  ) {
                    a(_, E.sibling),
                      (G = n(E, A.props)),
                      vn(G, A),
                      (G.return = _),
                      (_ = G);
                    break e;
                  }
                  a(_, E);
                  break;
                } else t(_, E);
                E = E.sibling;
              }
              A.type === w
                ? ((G = La(A.props.children, _.mode, G, A.key)),
                  (G.return = _),
                  (_ = G))
                : ((G = bs(A.type, A.key, A.props, null, _.mode, G)),
                  vn(G, A),
                  (G.return = _),
                  (_ = G));
            }
            return d(_);
          case L:
            e: {
              for (I = A.key; E !== null; ) {
                if (E.key === I)
                  if (
                    E.tag === 4 &&
                    E.stateNode.containerInfo === A.containerInfo &&
                    E.stateNode.implementation === A.implementation
                  ) {
                    a(_, E.sibling),
                      (G = n(E, A.children || [])),
                      (G.return = _),
                      (_ = G);
                    break e;
                  } else {
                    a(_, E);
                    break;
                  }
                else t(_, E);
                E = E.sibling;
              }
              (G = vr(A, _.mode, G)), (G.return = _), (_ = G);
            }
            return d(_);
          case ue:
            return (I = A._init), (A = I(A._payload)), _e(_, E, A, G);
        }
        if (pe(A)) return ie(_, E, A, G);
        if (Me(A)) {
          if (((I = Me(A)), typeof I != "function")) throw Error(c(150));
          return (A = I.call(A)), ne(_, E, A, G);
        }
        if (typeof A.then == "function") return _e(_, E, ks(A), G);
        if (A.$$typeof === U) return _e(_, E, Ns(_, A), G);
        Ls(_, A);
      }
      return (typeof A == "string" && A !== "") ||
        typeof A == "number" ||
        typeof A == "bigint"
        ? ((A = "" + A),
          E !== null && E.tag === 6
            ? (a(_, E.sibling), (G = n(E, A)), (G.return = _), (_ = G))
            : (a(_, E), (G = br(A, _.mode, G)), (G.return = _), (_ = G)),
          d(_))
        : a(_, E);
    }
    return function (_, E, A, G) {
      try {
        bn = 0;
        var I = _e(_, E, A, G);
        return (wl = null), I;
      } catch (ae) {
        if (ae === on || ae === Es) throw ae;
        var he = mt(29, ae, null, _.mode);
        return (he.lanes = G), (he.return = _), he;
      } finally {
      }
    };
  }
  var El = Mf(!0),
    Df = Mf(!1),
    Tt = M(null),
    Ut = null;
  function pa(e) {
    var t = e.alternate;
    K(Ge, Ge.current & 1),
      K(Tt, e),
      Ut === null &&
        (t === null || vl.current !== null || t.memoizedState !== null) &&
        (Ut = e);
  }
  function zf(e) {
    if (e.tag === 22) {
      if ((K(Ge, Ge.current), K(Tt, e), Ut === null)) {
        var t = e.alternate;
        t !== null && t.memoizedState !== null && (Ut = e);
      }
    } else xa();
  }
  function xa() {
    K(Ge, Ge.current), K(Tt, Tt.current);
  }
  function Ft(e) {
    J(Tt), Ut === e && (Ut = null), J(Ge);
  }
  var Ge = M(0);
  function Bs(e) {
    for (var t = e; t !== null; ) {
      if (t.tag === 13) {
        var a = t.memoizedState;
        if (
          a !== null &&
          ((a = a.dehydrated), a === null || a.data === "$?" || Vu(a))
        )
          return t;
      } else if (t.tag === 19 && t.memoizedProps.revealOrder !== void 0) {
        if ((t.flags & 128) !== 0) return t;
      } else if (t.child !== null) {
        (t.child.return = t), (t = t.child);
        continue;
      }
      if (t === e) break;
      for (; t.sibling === null; ) {
        if (t.return === null || t.return === e) return null;
        t = t.return;
      }
      (t.sibling.return = t.return), (t = t.sibling);
    }
    return null;
  }
  function Ir(e, t, a, l) {
    (t = e.memoizedState),
      (a = a(l, t)),
      (a = a == null ? t : v({}, t, a)),
      (e.memoizedState = a),
      e.lanes === 0 && (e.updateQueue.baseState = a);
  }
  var eu = {
    enqueueSetState: function (e, t, a) {
      e = e._reactInternals;
      var l = gt(),
        n = da(l);
      (n.payload = t),
        a != null && (n.callback = a),
        (t = ma(e, n, l)),
        t !== null && (yt(t, e, l), dn(t, e, l));
    },
    enqueueReplaceState: function (e, t, a) {
      e = e._reactInternals;
      var l = gt(),
        n = da(l);
      (n.tag = 1),
        (n.payload = t),
        a != null && (n.callback = a),
        (t = ma(e, n, l)),
        t !== null && (yt(t, e, l), dn(t, e, l));
    },
    enqueueForceUpdate: function (e, t) {
      e = e._reactInternals;
      var a = gt(),
        l = da(a);
      (l.tag = 2),
        t != null && (l.callback = t),
        (t = ma(e, l, a)),
        t !== null && (yt(t, e, a), dn(t, e, a));
    },
  };
  function Uf(e, t, a, l, n, i, d) {
    return (
      (e = e.stateNode),
      typeof e.shouldComponentUpdate == "function"
        ? e.shouldComponentUpdate(l, i, d)
        : t.prototype && t.prototype.isPureReactComponent
        ? !tn(a, l) || !tn(n, i)
        : !0
    );
  }
  function kf(e, t, a, l) {
    (e = t.state),
      typeof t.componentWillReceiveProps == "function" &&
        t.componentWillReceiveProps(a, l),
      typeof t.UNSAFE_componentWillReceiveProps == "function" &&
        t.UNSAFE_componentWillReceiveProps(a, l),
      t.state !== e && eu.enqueueReplaceState(t, t.state, null);
  }
  function Za(e, t) {
    var a = t;
    if ("ref" in t) {
      a = {};
      for (var l in t) l !== "ref" && (a[l] = t[l]);
    }
    if ((e = e.defaultProps)) {
      a === t && (a = v({}, a));
      for (var n in e) a[n] === void 0 && (a[n] = e[n]);
    }
    return a;
  }
  var Hs =
    typeof reportError == "function"
      ? reportError
      : function (e) {
          if (
            typeof window == "object" &&
            typeof window.ErrorEvent == "function"
          ) {
            var t = new window.ErrorEvent("error", {
              bubbles: !0,
              cancelable: !0,
              message:
                typeof e == "object" &&
                e !== null &&
                typeof e.message == "string"
                  ? String(e.message)
                  : String(e),
              error: e,
            });
            if (!window.dispatchEvent(t)) return;
          } else if (
            typeof process == "object" &&
            typeof process.emit == "function"
          ) {
            process.emit("uncaughtException", e);
            return;
          }
          console.error(e);
        };
  function Lf(e) {
    Hs(e);
  }
  function Bf(e) {
    console.error(e);
  }
  function Hf(e) {
    Hs(e);
  }
  function qs(e, t) {
    try {
      var a = e.onUncaughtError;
      a(t.value, { componentStack: t.stack });
    } catch (l) {
      setTimeout(function () {
        throw l;
      });
    }
  }
  function qf(e, t, a) {
    try {
      var l = e.onCaughtError;
      l(a.value, {
        componentStack: a.stack,
        errorBoundary: t.tag === 1 ? t.stateNode : null,
      });
    } catch (n) {
      setTimeout(function () {
        throw n;
      });
    }
  }
  function tu(e, t, a) {
    return (
      (a = da(a)),
      (a.tag = 3),
      (a.payload = { element: null }),
      (a.callback = function () {
        qs(e, t);
      }),
      a
    );
  }
  function Yf(e) {
    return (e = da(e)), (e.tag = 3), e;
  }
  function Vf(e, t, a, l) {
    var n = a.type.getDerivedStateFromError;
    if (typeof n == "function") {
      var i = l.value;
      (e.payload = function () {
        return n(i);
      }),
        (e.callback = function () {
          qf(t, a, l);
        });
    }
    var d = a.stateNode;
    d !== null &&
      typeof d.componentDidCatch == "function" &&
      (e.callback = function () {
        qf(t, a, l),
          typeof n != "function" &&
            (Sa === null ? (Sa = new Set([this])) : Sa.add(this));
        var h = l.stack;
        this.componentDidCatch(l.value, {
          componentStack: h !== null ? h : "",
        });
      });
  }
  function cp(e, t, a, l, n) {
    if (
      ((a.flags |= 32768),
      l !== null && typeof l == "object" && typeof l.then == "function")
    ) {
      if (
        ((t = a.alternate),
        t !== null && rn(t, a, n, !0),
        (a = Tt.current),
        a !== null)
      ) {
        switch (a.tag) {
          case 13:
            return (
              Ut === null ? Eu() : a.alternate === null && Le === 0 && (Le = 3),
              (a.flags &= -257),
              (a.flags |= 65536),
              (a.lanes = n),
              l === Cr
                ? (a.flags |= 16384)
                : ((t = a.updateQueue),
                  t === null ? (a.updateQueue = new Set([l])) : t.add(l),
                  _u(e, l, n)),
              !1
            );
          case 22:
            return (
              (a.flags |= 65536),
              l === Cr
                ? (a.flags |= 16384)
                : ((t = a.updateQueue),
                  t === null
                    ? ((t = {
                        transitions: null,
                        markerInstances: null,
                        retryQueue: new Set([l]),
                      }),
                      (a.updateQueue = t))
                    : ((a = t.retryQueue),
                      a === null ? (t.retryQueue = new Set([l])) : a.add(l)),
                  _u(e, l, n)),
              !1
            );
        }
        throw Error(c(435, a.tag));
      }
      return _u(e, l, n), Eu(), !1;
    }
    if (Se)
      return (
        (t = Tt.current),
        t !== null
          ? ((t.flags & 65536) === 0 && (t.flags |= 256),
            (t.flags |= 65536),
            (t.lanes = n),
            l !== Nr && ((e = Error(c(422), { cause: l })), sn(St(e, a))))
          : (l !== Nr && ((t = Error(c(423), { cause: l })), sn(St(t, a))),
            (e = e.current.alternate),
            (e.flags |= 65536),
            (n &= -n),
            (e.lanes |= n),
            (l = St(l, a)),
            (n = tu(e.stateNode, l, n)),
            Dr(e, n),
            Le !== 4 && (Le = 2)),
        !1
      );
    var i = Error(c(520), { cause: l });
    if (
      ((i = St(i, a)),
      _n === null ? (_n = [i]) : _n.push(i),
      Le !== 4 && (Le = 2),
      t === null)
    )
      return !0;
    (l = St(l, a)), (a = t);
    do {
      switch (a.tag) {
        case 3:
          return (
            (a.flags |= 65536),
            (e = n & -n),
            (a.lanes |= e),
            (e = tu(a.stateNode, l, e)),
            Dr(a, e),
            !1
          );
        case 1:
          if (
            ((t = a.type),
            (i = a.stateNode),
            (a.flags & 128) === 0 &&
              (typeof t.getDerivedStateFromError == "function" ||
                (i !== null &&
                  typeof i.componentDidCatch == "function" &&
                  (Sa === null || !Sa.has(i)))))
          )
            return (
              (a.flags |= 65536),
              (n &= -n),
              (a.lanes |= n),
              (n = Yf(n)),
              Vf(n, e, a, l),
              Dr(a, n),
              !1
            );
      }
      a = a.return;
    } while (a !== null);
    return !1;
  }
  var Gf = Error(c(461)),
    Qe = !1;
  function Je(e, t, a, l) {
    t.child = e === null ? Df(t, null, a, l) : El(t, e.child, a, l);
  }
  function Xf(e, t, a, l, n) {
    a = a.render;
    var i = t.ref;
    if ("ref" in l) {
      var d = {};
      for (var h in l) h !== "ref" && (d[h] = l[h]);
    } else d = l;
    return (
      Ga(t),
      (l = Br(e, t, a, d, i, n)),
      (h = Hr()),
      e !== null && !Qe
        ? (qr(e, t, n), Wt(e, t, n))
        : (Se && h && jr(t), (t.flags |= 1), Je(e, t, l, n), t.child)
    );
  }
  function Qf(e, t, a, l, n) {
    if (e === null) {
      var i = a.type;
      return typeof i == "function" &&
        !yr(i) &&
        i.defaultProps === void 0 &&
        a.compare === null
        ? ((t.tag = 15), (t.type = i), Zf(e, t, i, l, n))
        : ((e = bs(a.type, null, l, t, t.mode, n)),
          (e.ref = t.ref),
          (e.return = t),
          (t.child = e));
    }
    if (((i = e.child), !cu(e, n))) {
      var d = i.memoizedProps;
      if (
        ((a = a.compare), (a = a !== null ? a : tn), a(d, l) && e.ref === t.ref)
      )
        return Wt(e, t, n);
    }
    return (
      (t.flags |= 1),
      (e = Xt(i, l)),
      (e.ref = t.ref),
      (e.return = t),
      (t.child = e)
    );
  }
  function Zf(e, t, a, l, n) {
    if (e !== null) {
      var i = e.memoizedProps;
      if (tn(i, l) && e.ref === t.ref)
        if (((Qe = !1), (t.pendingProps = l = i), cu(e, n)))
          (e.flags & 131072) !== 0 && (Qe = !0);
        else return (t.lanes = e.lanes), Wt(e, t, n);
    }
    return au(e, t, a, l, n);
  }
  function Kf(e, t, a) {
    var l = t.pendingProps,
      n = l.children,
      i = e !== null ? e.memoizedState : null;
    if (l.mode === "hidden") {
      if ((t.flags & 128) !== 0) {
        if (((l = i !== null ? i.baseLanes | a : a), e !== null)) {
          for (n = t.child = e.child, i = 0; n !== null; )
            (i = i | n.lanes | n.childLanes), (n = n.sibling);
          t.childLanes = i & ~l;
        } else (t.childLanes = 0), (t.child = null);
        return Jf(e, t, l, a);
      }
      if ((a & 536870912) !== 0)
        (t.memoizedState = { baseLanes: 0, cachePool: null }),
          e !== null && ws(t, i !== null ? i.cachePool : null),
          i !== null ? Qo(t, i) : Ur(),
          zf(t);
      else
        return (
          (t.lanes = t.childLanes = 536870912),
          Jf(e, t, i !== null ? i.baseLanes | a : a, a)
        );
    } else
      i !== null
        ? (ws(t, i.cachePool), Qo(t, i), xa(), (t.memoizedState = null))
        : (e !== null && ws(t, null), Ur(), xa());
    return Je(e, t, n, a), t.child;
  }
  function Jf(e, t, a, l) {
    var n = Rr();
    return (
      (n = n === null ? null : { parent: Ve._currentValue, pool: n }),
      (t.memoizedState = { baseLanes: a, cachePool: n }),
      e !== null && ws(t, null),
      Ur(),
      zf(t),
      e !== null && rn(e, t, l, !0),
      null
    );
  }
  function Ys(e, t) {
    var a = t.ref;
    if (a === null) e !== null && e.ref !== null && (t.flags |= 4194816);
    else {
      if (typeof a != "function" && typeof a != "object") throw Error(c(284));
      (e === null || e.ref !== a) && (t.flags |= 4194816);
    }
  }
  function au(e, t, a, l, n) {
    return (
      Ga(t),
      (a = Br(e, t, a, l, void 0, n)),
      (l = Hr()),
      e !== null && !Qe
        ? (qr(e, t, n), Wt(e, t, n))
        : (Se && l && jr(t), (t.flags |= 1), Je(e, t, a, n), t.child)
    );
  }
  function $f(e, t, a, l, n, i) {
    return (
      Ga(t),
      (t.updateQueue = null),
      (a = Ko(t, l, a, n)),
      Zo(e),
      (l = Hr()),
      e !== null && !Qe
        ? (qr(e, t, i), Wt(e, t, i))
        : (Se && l && jr(t), (t.flags |= 1), Je(e, t, a, i), t.child)
    );
  }
  function Ff(e, t, a, l, n) {
    if ((Ga(t), t.stateNode === null)) {
      var i = pl,
        d = a.contextType;
      typeof d == "object" && d !== null && (i = Pe(d)),
        (i = new a(l, i)),
        (t.memoizedState =
          i.state !== null && i.state !== void 0 ? i.state : null),
        (i.updater = eu),
        (t.stateNode = i),
        (i._reactInternals = t),
        (i = t.stateNode),
        (i.props = l),
        (i.state = t.memoizedState),
        (i.refs = {}),
        Or(t),
        (d = a.contextType),
        (i.context = typeof d == "object" && d !== null ? Pe(d) : pl),
        (i.state = t.memoizedState),
        (d = a.getDerivedStateFromProps),
        typeof d == "function" && (Ir(t, a, d, l), (i.state = t.memoizedState)),
        typeof a.getDerivedStateFromProps == "function" ||
          typeof i.getSnapshotBeforeUpdate == "function" ||
          (typeof i.UNSAFE_componentWillMount != "function" &&
            typeof i.componentWillMount != "function") ||
          ((d = i.state),
          typeof i.componentWillMount == "function" && i.componentWillMount(),
          typeof i.UNSAFE_componentWillMount == "function" &&
            i.UNSAFE_componentWillMount(),
          d !== i.state && eu.enqueueReplaceState(i, i.state, null),
          hn(t, l, i, n),
          mn(),
          (i.state = t.memoizedState)),
        typeof i.componentDidMount == "function" && (t.flags |= 4194308),
        (l = !0);
    } else if (e === null) {
      i = t.stateNode;
      var h = t.memoizedProps,
        j = Za(a, h);
      i.props = j;
      var O = i.context,
        V = a.contextType;
      (d = pl), typeof V == "object" && V !== null && (d = Pe(V));
      var X = a.getDerivedStateFromProps;
      (V =
        typeof X == "function" ||
        typeof i.getSnapshotBeforeUpdate == "function"),
        (h = t.pendingProps !== h),
        V ||
          (typeof i.UNSAFE_componentWillReceiveProps != "function" &&
            typeof i.componentWillReceiveProps != "function") ||
          ((h || O !== d) && kf(t, i, l, d)),
        (fa = !1);
      var z = t.memoizedState;
      (i.state = z),
        hn(t, l, i, n),
        mn(),
        (O = t.memoizedState),
        h || z !== O || fa
          ? (typeof X == "function" && (Ir(t, a, X, l), (O = t.memoizedState)),
            (j = fa || Uf(t, a, j, l, z, O, d))
              ? (V ||
                  (typeof i.UNSAFE_componentWillMount != "function" &&
                    typeof i.componentWillMount != "function") ||
                  (typeof i.componentWillMount == "function" &&
                    i.componentWillMount(),
                  typeof i.UNSAFE_componentWillMount == "function" &&
                    i.UNSAFE_componentWillMount()),
                typeof i.componentDidMount == "function" &&
                  (t.flags |= 4194308))
              : (typeof i.componentDidMount == "function" &&
                  (t.flags |= 4194308),
                (t.memoizedProps = l),
                (t.memoizedState = O)),
            (i.props = l),
            (i.state = O),
            (i.context = d),
            (l = j))
          : (typeof i.componentDidMount == "function" && (t.flags |= 4194308),
            (l = !1));
    } else {
      (i = t.stateNode),
        Mr(e, t),
        (d = t.memoizedProps),
        (V = Za(a, d)),
        (i.props = V),
        (X = t.pendingProps),
        (z = i.context),
        (O = a.contextType),
        (j = pl),
        typeof O == "object" && O !== null && (j = Pe(O)),
        (h = a.getDerivedStateFromProps),
        (O =
          typeof h == "function" ||
          typeof i.getSnapshotBeforeUpdate == "function") ||
          (typeof i.UNSAFE_componentWillReceiveProps != "function" &&
            typeof i.componentWillReceiveProps != "function") ||
          ((d !== X || z !== j) && kf(t, i, l, j)),
        (fa = !1),
        (z = t.memoizedState),
        (i.state = z),
        hn(t, l, i, n),
        mn();
      var k = t.memoizedState;
      d !== X ||
      z !== k ||
      fa ||
      (e !== null && e.dependencies !== null && Ss(e.dependencies))
        ? (typeof h == "function" && (Ir(t, a, h, l), (k = t.memoizedState)),
          (V =
            fa ||
            Uf(t, a, V, l, z, k, j) ||
            (e !== null && e.dependencies !== null && Ss(e.dependencies)))
            ? (O ||
                (typeof i.UNSAFE_componentWillUpdate != "function" &&
                  typeof i.componentWillUpdate != "function") ||
                (typeof i.componentWillUpdate == "function" &&
                  i.componentWillUpdate(l, k, j),
                typeof i.UNSAFE_componentWillUpdate == "function" &&
                  i.UNSAFE_componentWillUpdate(l, k, j)),
              typeof i.componentDidUpdate == "function" && (t.flags |= 4),
              typeof i.getSnapshotBeforeUpdate == "function" &&
                (t.flags |= 1024))
            : (typeof i.componentDidUpdate != "function" ||
                (d === e.memoizedProps && z === e.memoizedState) ||
                (t.flags |= 4),
              typeof i.getSnapshotBeforeUpdate != "function" ||
                (d === e.memoizedProps && z === e.memoizedState) ||
                (t.flags |= 1024),
              (t.memoizedProps = l),
              (t.memoizedState = k)),
          (i.props = l),
          (i.state = k),
          (i.context = j),
          (l = V))
        : (typeof i.componentDidUpdate != "function" ||
            (d === e.memoizedProps && z === e.memoizedState) ||
            (t.flags |= 4),
          typeof i.getSnapshotBeforeUpdate != "function" ||
            (d === e.memoizedProps && z === e.memoizedState) ||
            (t.flags |= 1024),
          (l = !1));
    }
    return (
      (i = l),
      Ys(e, t),
      (l = (t.flags & 128) !== 0),
      i || l
        ? ((i = t.stateNode),
          (a =
            l && typeof a.getDerivedStateFromError != "function"
              ? null
              : i.render()),
          (t.flags |= 1),
          e !== null && l
            ? ((t.child = El(t, e.child, null, n)),
              (t.child = El(t, null, a, n)))
            : Je(e, t, a, n),
          (t.memoizedState = i.state),
          (e = t.child))
        : (e = Wt(e, t, n)),
      e
    );
  }
  function Wf(e, t, a, l) {
    return nn(), (t.flags |= 256), Je(e, t, a, l), t.child;
  }
  var lu = {
    dehydrated: null,
    treeContext: null,
    retryLane: 0,
    hydrationErrors: null,
  };
  function nu(e) {
    return { baseLanes: e, cachePool: Lo() };
  }
  function su(e, t, a) {
    return (e = e !== null ? e.childLanes & ~a : 0), t && (e |= _t), e;
  }
  function Pf(e, t, a) {
    var l = t.pendingProps,
      n = !1,
      i = (t.flags & 128) !== 0,
      d;
    if (
      ((d = i) ||
        (d =
          e !== null && e.memoizedState === null ? !1 : (Ge.current & 2) !== 0),
      d && ((n = !0), (t.flags &= -129)),
      (d = (t.flags & 32) !== 0),
      (t.flags &= -33),
      e === null)
    ) {
      if (Se) {
        if ((n ? pa(t) : xa(), Se)) {
          var h = ke,
            j;
          if ((j = h)) {
            e: {
              for (j = h, h = zt; j.nodeType !== 8; ) {
                if (!h) {
                  h = null;
                  break e;
                }
                if (((j = Ot(j.nextSibling)), j === null)) {
                  h = null;
                  break e;
                }
              }
              h = j;
            }
            h !== null
              ? ((t.memoizedState = {
                  dehydrated: h,
                  treeContext: Ba !== null ? { id: Qt, overflow: Zt } : null,
                  retryLane: 536870912,
                  hydrationErrors: null,
                }),
                (j = mt(18, null, null, 0)),
                (j.stateNode = h),
                (j.return = t),
                (t.child = j),
                (at = t),
                (ke = null),
                (j = !0))
              : (j = !1);
          }
          j || Ya(t);
        }
        if (
          ((h = t.memoizedState),
          h !== null && ((h = h.dehydrated), h !== null))
        )
          return Vu(h) ? (t.lanes = 32) : (t.lanes = 536870912), null;
        Ft(t);
      }
      return (
        (h = l.children),
        (l = l.fallback),
        n
          ? (xa(),
            (n = t.mode),
            (h = Vs({ mode: "hidden", children: h }, n)),
            (l = La(l, n, a, null)),
            (h.return = t),
            (l.return = t),
            (h.sibling = l),
            (t.child = h),
            (n = t.child),
            (n.memoizedState = nu(a)),
            (n.childLanes = su(e, d, a)),
            (t.memoizedState = lu),
            l)
          : (pa(t), iu(t, h))
      );
    }
    if (
      ((j = e.memoizedState), j !== null && ((h = j.dehydrated), h !== null))
    ) {
      if (i)
        t.flags & 256
          ? (pa(t), (t.flags &= -257), (t = ru(e, t, a)))
          : t.memoizedState !== null
          ? (xa(), (t.child = e.child), (t.flags |= 128), (t = null))
          : (xa(),
            (n = l.fallback),
            (h = t.mode),
            (l = Vs({ mode: "visible", children: l.children }, h)),
            (n = La(n, h, a, null)),
            (n.flags |= 2),
            (l.return = t),
            (n.return = t),
            (l.sibling = n),
            (t.child = l),
            El(t, e.child, null, a),
            (l = t.child),
            (l.memoizedState = nu(a)),
            (l.childLanes = su(e, d, a)),
            (t.memoizedState = lu),
            (t = n));
      else if ((pa(t), Vu(h))) {
        if (((d = h.nextSibling && h.nextSibling.dataset), d)) var O = d.dgst;
        (d = O),
          (l = Error(c(419))),
          (l.stack = ""),
          (l.digest = d),
          sn({ value: l, source: null, stack: null }),
          (t = ru(e, t, a));
      } else if (
        (Qe || rn(e, t, a, !1), (d = (a & e.childLanes) !== 0), Qe || d)
      ) {
        if (
          ((d = Ce),
          d !== null &&
            ((l = a & -a),
            (l = (l & 42) !== 0 ? 1 : Gi(l)),
            (l = (l & (d.suspendedLanes | a)) !== 0 ? 0 : l),
            l !== 0 && l !== j.retryLane))
        )
          throw ((j.retryLane = l), hl(e, l), yt(d, e, l), Gf);
        h.data === "$?" || Eu(), (t = ru(e, t, a));
      } else
        h.data === "$?"
          ? ((t.flags |= 192), (t.child = e.child), (t = null))
          : ((e = j.treeContext),
            (ke = Ot(h.nextSibling)),
            (at = t),
            (Se = !0),
            (qa = null),
            (zt = !1),
            e !== null &&
              ((wt[Et++] = Qt),
              (wt[Et++] = Zt),
              (wt[Et++] = Ba),
              (Qt = e.id),
              (Zt = e.overflow),
              (Ba = t)),
            (t = iu(t, l.children)),
            (t.flags |= 4096));
      return t;
    }
    return n
      ? (xa(),
        (n = l.fallback),
        (h = t.mode),
        (j = e.child),
        (O = j.sibling),
        (l = Xt(j, { mode: "hidden", children: l.children })),
        (l.subtreeFlags = j.subtreeFlags & 65011712),
        O !== null ? (n = Xt(O, n)) : ((n = La(n, h, a, null)), (n.flags |= 2)),
        (n.return = t),
        (l.return = t),
        (l.sibling = n),
        (t.child = l),
        (l = n),
        (n = t.child),
        (h = e.child.memoizedState),
        h === null
          ? (h = nu(a))
          : ((j = h.cachePool),
            j !== null
              ? ((O = Ve._currentValue),
                (j = j.parent !== O ? { parent: O, pool: O } : j))
              : (j = Lo()),
            (h = { baseLanes: h.baseLanes | a, cachePool: j })),
        (n.memoizedState = h),
        (n.childLanes = su(e, d, a)),
        (t.memoizedState = lu),
        l)
      : (pa(t),
        (a = e.child),
        (e = a.sibling),
        (a = Xt(a, { mode: "visible", children: l.children })),
        (a.return = t),
        (a.sibling = null),
        e !== null &&
          ((d = t.deletions),
          d === null ? ((t.deletions = [e]), (t.flags |= 16)) : d.push(e)),
        (t.child = a),
        (t.memoizedState = null),
        a);
  }
  function iu(e, t) {
    return (
      (t = Vs({ mode: "visible", children: t }, e.mode)),
      (t.return = e),
      (e.child = t)
    );
  }
  function Vs(e, t) {
    return (
      (e = mt(22, e, null, t)),
      (e.lanes = 0),
      (e.stateNode = {
        _visibility: 1,
        _pendingMarkers: null,
        _retryCache: null,
        _transitions: null,
      }),
      e
    );
  }
  function ru(e, t, a) {
    return (
      El(t, e.child, null, a),
      (e = iu(t, t.pendingProps.children)),
      (e.flags |= 2),
      (t.memoizedState = null),
      e
    );
  }
  function If(e, t, a) {
    e.lanes |= t;
    var l = e.alternate;
    l !== null && (l.lanes |= t), Er(e.return, t, a);
  }
  function uu(e, t, a, l, n) {
    var i = e.memoizedState;
    i === null
      ? (e.memoizedState = {
          isBackwards: t,
          rendering: null,
          renderingStartTime: 0,
          last: l,
          tail: a,
          tailMode: n,
        })
      : ((i.isBackwards = t),
        (i.rendering = null),
        (i.renderingStartTime = 0),
        (i.last = l),
        (i.tail = a),
        (i.tailMode = n));
  }
  function ed(e, t, a) {
    var l = t.pendingProps,
      n = l.revealOrder,
      i = l.tail;
    if ((Je(e, t, l.children, a), (l = Ge.current), (l & 2) !== 0))
      (l = (l & 1) | 2), (t.flags |= 128);
    else {
      if (e !== null && (e.flags & 128) !== 0)
        e: for (e = t.child; e !== null; ) {
          if (e.tag === 13) e.memoizedState !== null && If(e, a, t);
          else if (e.tag === 19) If(e, a, t);
          else if (e.child !== null) {
            (e.child.return = e), (e = e.child);
            continue;
          }
          if (e === t) break e;
          for (; e.sibling === null; ) {
            if (e.return === null || e.return === t) break e;
            e = e.return;
          }
          (e.sibling.return = e.return), (e = e.sibling);
        }
      l &= 1;
    }
    switch ((K(Ge, l), n)) {
      case "forwards":
        for (a = t.child, n = null; a !== null; )
          (e = a.alternate),
            e !== null && Bs(e) === null && (n = a),
            (a = a.sibling);
        (a = n),
          a === null
            ? ((n = t.child), (t.child = null))
            : ((n = a.sibling), (a.sibling = null)),
          uu(t, !1, n, a, i);
        break;
      case "backwards":
        for (a = null, n = t.child, t.child = null; n !== null; ) {
          if (((e = n.alternate), e !== null && Bs(e) === null)) {
            t.child = n;
            break;
          }
          (e = n.sibling), (n.sibling = a), (a = n), (n = e);
        }
        uu(t, !0, a, null, i);
        break;
      case "together":
        uu(t, !1, null, null, void 0);
        break;
      default:
        t.memoizedState = null;
    }
    return t.child;
  }
  function Wt(e, t, a) {
    if (
      (e !== null && (t.dependencies = e.dependencies),
      (ja |= t.lanes),
      (a & t.childLanes) === 0)
    )
      if (e !== null) {
        if ((rn(e, t, a, !1), (a & t.childLanes) === 0)) return null;
      } else return null;
    if (e !== null && t.child !== e.child) throw Error(c(153));
    if (t.child !== null) {
      for (
        e = t.child, a = Xt(e, e.pendingProps), t.child = a, a.return = t;
        e.sibling !== null;

      )
        (e = e.sibling),
          (a = a.sibling = Xt(e, e.pendingProps)),
          (a.return = t);
      a.sibling = null;
    }
    return t.child;
  }
  function cu(e, t) {
    return (e.lanes & t) !== 0
      ? !0
      : ((e = e.dependencies), !!(e !== null && Ss(e)));
  }
  function op(e, t, a) {
    switch (t.tag) {
      case 3:
        Re(t, t.stateNode.containerInfo),
          oa(t, Ve, e.memoizedState.cache),
          nn();
        break;
      case 27:
      case 5:
        Bi(t);
        break;
      case 4:
        Re(t, t.stateNode.containerInfo);
        break;
      case 10:
        oa(t, t.type, t.memoizedProps.value);
        break;
      case 13:
        var l = t.memoizedState;
        if (l !== null)
          return l.dehydrated !== null
            ? (pa(t), (t.flags |= 128), null)
            : (a & t.child.childLanes) !== 0
            ? Pf(e, t, a)
            : (pa(t), (e = Wt(e, t, a)), e !== null ? e.sibling : null);
        pa(t);
        break;
      case 19:
        var n = (e.flags & 128) !== 0;
        if (
          ((l = (a & t.childLanes) !== 0),
          l || (rn(e, t, a, !1), (l = (a & t.childLanes) !== 0)),
          n)
        ) {
          if (l) return ed(e, t, a);
          t.flags |= 128;
        }
        if (
          ((n = t.memoizedState),
          n !== null &&
            ((n.rendering = null), (n.tail = null), (n.lastEffect = null)),
          K(Ge, Ge.current),
          l)
        )
          break;
        return null;
      case 22:
      case 23:
        return (t.lanes = 0), Kf(e, t, a);
      case 24:
        oa(t, Ve, e.memoizedState.cache);
    }
    return Wt(e, t, a);
  }
  function td(e, t, a) {
    if (e !== null)
      if (e.memoizedProps !== t.pendingProps) Qe = !0;
      else {
        if (!cu(e, a) && (t.flags & 128) === 0) return (Qe = !1), op(e, t, a);
        Qe = (e.flags & 131072) !== 0;
      }
    else (Qe = !1), Se && (t.flags & 1048576) !== 0 && Co(t, js, t.index);
    switch (((t.lanes = 0), t.tag)) {
      case 16:
        e: {
          e = t.pendingProps;
          var l = t.elementType,
            n = l._init;
          if (((l = n(l._payload)), (t.type = l), typeof l == "function"))
            yr(l)
              ? ((e = Za(l, e)), (t.tag = 1), (t = Ff(null, t, l, e, a)))
              : ((t.tag = 0), (t = au(null, t, l, e, a)));
          else {
            if (l != null) {
              if (((n = l.$$typeof), n === q)) {
                (t.tag = 11), (t = Xf(null, t, l, e, a));
                break e;
              } else if (n === re) {
                (t.tag = 14), (t = Qf(null, t, l, e, a));
                break e;
              }
            }
            throw ((t = oe(l) || l), Error(c(306, t, "")));
          }
        }
        return t;
      case 0:
        return au(e, t, t.type, t.pendingProps, a);
      case 1:
        return (l = t.type), (n = Za(l, t.pendingProps)), Ff(e, t, l, n, a);
      case 3:
        e: {
          if ((Re(t, t.stateNode.containerInfo), e === null))
            throw Error(c(387));
          l = t.pendingProps;
          var i = t.memoizedState;
          (n = i.element), Mr(e, t), hn(t, l, null, a);
          var d = t.memoizedState;
          if (
            ((l = d.cache),
            oa(t, Ve, l),
            l !== i.cache && Tr(t, [Ve], a, !0),
            mn(),
            (l = d.element),
            i.isDehydrated)
          )
            if (
              ((i = { element: l, isDehydrated: !1, cache: d.cache }),
              (t.updateQueue.baseState = i),
              (t.memoizedState = i),
              t.flags & 256)
            ) {
              t = Wf(e, t, l, a);
              break e;
            } else if (l !== n) {
              (n = St(Error(c(424)), t)), sn(n), (t = Wf(e, t, l, a));
              break e;
            } else {
              switch (((e = t.stateNode.containerInfo), e.nodeType)) {
                case 9:
                  e = e.body;
                  break;
                default:
                  e = e.nodeName === "HTML" ? e.ownerDocument.body : e;
              }
              for (
                ke = Ot(e.firstChild),
                  at = t,
                  Se = !0,
                  qa = null,
                  zt = !0,
                  a = Df(t, null, l, a),
                  t.child = a;
                a;

              )
                (a.flags = (a.flags & -3) | 4096), (a = a.sibling);
            }
          else {
            if ((nn(), l === n)) {
              t = Wt(e, t, a);
              break e;
            }
            Je(e, t, l, a);
          }
          t = t.child;
        }
        return t;
      case 26:
        return (
          Ys(e, t),
          e === null
            ? (a = sm(t.type, null, t.pendingProps, null))
              ? (t.memoizedState = a)
              : Se ||
                ((a = t.type),
                (e = t.pendingProps),
                (l = ai(te.current).createElement(a)),
                (l[We] = t),
                (l[lt] = e),
                Fe(l, a, e),
                Xe(l),
                (t.stateNode = l))
            : (t.memoizedState = sm(
                t.type,
                e.memoizedProps,
                t.pendingProps,
                e.memoizedState
              )),
          null
        );
      case 27:
        return (
          Bi(t),
          e === null &&
            Se &&
            ((l = t.stateNode = am(t.type, t.pendingProps, te.current)),
            (at = t),
            (zt = !0),
            (n = ke),
            Ea(t.type) ? ((Gu = n), (ke = Ot(l.firstChild))) : (ke = n)),
          Je(e, t, t.pendingProps.children, a),
          Ys(e, t),
          e === null && (t.flags |= 4194304),
          t.child
        );
      case 5:
        return (
          e === null &&
            Se &&
            ((n = l = ke) &&
              ((l = Bp(l, t.type, t.pendingProps, zt)),
              l !== null
                ? ((t.stateNode = l),
                  (at = t),
                  (ke = Ot(l.firstChild)),
                  (zt = !1),
                  (n = !0))
                : (n = !1)),
            n || Ya(t)),
          Bi(t),
          (n = t.type),
          (i = t.pendingProps),
          (d = e !== null ? e.memoizedProps : null),
          (l = i.children),
          Hu(n, i) ? (l = null) : d !== null && Hu(n, d) && (t.flags |= 32),
          t.memoizedState !== null &&
            ((n = Br(e, t, ap, null, null, a)), (kn._currentValue = n)),
          Ys(e, t),
          Je(e, t, l, a),
          t.child
        );
      case 6:
        return (
          e === null &&
            Se &&
            ((e = a = ke) &&
              ((a = Hp(a, t.pendingProps, zt)),
              a !== null
                ? ((t.stateNode = a), (at = t), (ke = null), (e = !0))
                : (e = !1)),
            e || Ya(t)),
          null
        );
      case 13:
        return Pf(e, t, a);
      case 4:
        return (
          Re(t, t.stateNode.containerInfo),
          (l = t.pendingProps),
          e === null ? (t.child = El(t, null, l, a)) : Je(e, t, l, a),
          t.child
        );
      case 11:
        return Xf(e, t, t.type, t.pendingProps, a);
      case 7:
        return Je(e, t, t.pendingProps, a), t.child;
      case 8:
        return Je(e, t, t.pendingProps.children, a), t.child;
      case 12:
        return Je(e, t, t.pendingProps.children, a), t.child;
      case 10:
        return (
          (l = t.pendingProps),
          oa(t, t.type, l.value),
          Je(e, t, l.children, a),
          t.child
        );
      case 9:
        return (
          (n = t.type._context),
          (l = t.pendingProps.children),
          Ga(t),
          (n = Pe(n)),
          (l = l(n)),
          (t.flags |= 1),
          Je(e, t, l, a),
          t.child
        );
      case 14:
        return Qf(e, t, t.type, t.pendingProps, a);
      case 15:
        return Zf(e, t, t.type, t.pendingProps, a);
      case 19:
        return ed(e, t, a);
      case 31:
        return (
          (l = t.pendingProps),
          (a = t.mode),
          (l = { mode: l.mode, children: l.children }),
          e === null
            ? ((a = Vs(l, a)),
              (a.ref = t.ref),
              (t.child = a),
              (a.return = t),
              (t = a))
            : ((a = Xt(e.child, l)),
              (a.ref = t.ref),
              (t.child = a),
              (a.return = t),
              (t = a)),
          t
        );
      case 22:
        return Kf(e, t, a);
      case 24:
        return (
          Ga(t),
          (l = Pe(Ve)),
          e === null
            ? ((n = Rr()),
              n === null &&
                ((n = Ce),
                (i = _r()),
                (n.pooledCache = i),
                i.refCount++,
                i !== null && (n.pooledCacheLanes |= a),
                (n = i)),
              (t.memoizedState = { parent: l, cache: n }),
              Or(t),
              oa(t, Ve, n))
            : ((e.lanes & a) !== 0 && (Mr(e, t), hn(t, null, null, a), mn()),
              (n = e.memoizedState),
              (i = t.memoizedState),
              n.parent !== l
                ? ((n = { parent: l, cache: l }),
                  (t.memoizedState = n),
                  t.lanes === 0 &&
                    (t.memoizedState = t.updateQueue.baseState = n),
                  oa(t, Ve, l))
                : ((l = i.cache),
                  oa(t, Ve, l),
                  l !== n.cache && Tr(t, [Ve], a, !0))),
          Je(e, t, t.pendingProps.children, a),
          t.child
        );
      case 29:
        throw t.pendingProps;
    }
    throw Error(c(156, t.tag));
  }
  function Pt(e) {
    e.flags |= 4;
  }
  function ad(e, t) {
    if (t.type !== "stylesheet" || (t.state.loading & 4) !== 0)
      e.flags &= -16777217;
    else if (((e.flags |= 16777216), !om(t))) {
      if (
        ((t = Tt.current),
        t !== null &&
          ((ye & 4194048) === ye
            ? Ut !== null
            : ((ye & 62914560) !== ye && (ye & 536870912) === 0) || t !== Ut))
      )
        throw ((fn = Cr), Bo);
      e.flags |= 8192;
    }
  }
  function Gs(e, t) {
    t !== null && (e.flags |= 4),
      e.flags & 16384 &&
        ((t = e.tag !== 22 ? Mc() : 536870912), (e.lanes |= t), (Rl |= t));
  }
  function jn(e, t) {
    if (!Se)
      switch (e.tailMode) {
        case "hidden":
          t = e.tail;
          for (var a = null; t !== null; )
            t.alternate !== null && (a = t), (t = t.sibling);
          a === null ? (e.tail = null) : (a.sibling = null);
          break;
        case "collapsed":
          a = e.tail;
          for (var l = null; a !== null; )
            a.alternate !== null && (l = a), (a = a.sibling);
          l === null
            ? t || e.tail === null
              ? (e.tail = null)
              : (e.tail.sibling = null)
            : (l.sibling = null);
      }
  }
  function De(e) {
    var t = e.alternate !== null && e.alternate.child === e.child,
      a = 0,
      l = 0;
    if (t)
      for (var n = e.child; n !== null; )
        (a |= n.lanes | n.childLanes),
          (l |= n.subtreeFlags & 65011712),
          (l |= n.flags & 65011712),
          (n.return = e),
          (n = n.sibling);
    else
      for (n = e.child; n !== null; )
        (a |= n.lanes | n.childLanes),
          (l |= n.subtreeFlags),
          (l |= n.flags),
          (n.return = e),
          (n = n.sibling);
    return (e.subtreeFlags |= l), (e.childLanes = a), t;
  }
  function fp(e, t, a) {
    var l = t.pendingProps;
    switch ((Sr(t), t.tag)) {
      case 31:
      case 16:
      case 15:
      case 0:
      case 11:
      case 7:
      case 8:
      case 12:
      case 9:
      case 14:
        return De(t), null;
      case 1:
        return De(t), null;
      case 3:
        return (
          (a = t.stateNode),
          (l = null),
          e !== null && (l = e.memoizedState.cache),
          t.memoizedState.cache !== l && (t.flags |= 2048),
          Jt(Ve),
          ia(),
          a.pendingContext &&
            ((a.context = a.pendingContext), (a.pendingContext = null)),
          (e === null || e.child === null) &&
            (ln(t)
              ? Pt(t)
              : e === null ||
                (e.memoizedState.isDehydrated && (t.flags & 256) === 0) ||
                ((t.flags |= 1024), Do())),
          De(t),
          null
        );
      case 26:
        return (
          (a = t.memoizedState),
          e === null
            ? (Pt(t),
              a !== null ? (De(t), ad(t, a)) : (De(t), (t.flags &= -16777217)))
            : a
            ? a !== e.memoizedState
              ? (Pt(t), De(t), ad(t, a))
              : (De(t), (t.flags &= -16777217))
            : (e.memoizedProps !== l && Pt(t), De(t), (t.flags &= -16777217)),
          null
        );
      case 27:
        es(t), (a = te.current);
        var n = t.type;
        if (e !== null && t.stateNode != null) e.memoizedProps !== l && Pt(t);
        else {
          if (!l) {
            if (t.stateNode === null) throw Error(c(166));
            return De(t), null;
          }
          (e = P.current),
            ln(t) ? Oo(t) : ((e = am(n, l, a)), (t.stateNode = e), Pt(t));
        }
        return De(t), null;
      case 5:
        if ((es(t), (a = t.type), e !== null && t.stateNode != null))
          e.memoizedProps !== l && Pt(t);
        else {
          if (!l) {
            if (t.stateNode === null) throw Error(c(166));
            return De(t), null;
          }
          if (((e = P.current), ln(t))) Oo(t);
          else {
            switch (((n = ai(te.current)), e)) {
              case 1:
                e = n.createElementNS("http://www.w3.org/2000/svg", a);
                break;
              case 2:
                e = n.createElementNS("http://www.w3.org/1998/Math/MathML", a);
                break;
              default:
                switch (a) {
                  case "svg":
                    e = n.createElementNS("http://www.w3.org/2000/svg", a);
                    break;
                  case "math":
                    e = n.createElementNS(
                      "http://www.w3.org/1998/Math/MathML",
                      a
                    );
                    break;
                  case "script":
                    (e = n.createElement("div")),
                      (e.innerHTML = "<script></script>"),
                      (e = e.removeChild(e.firstChild));
                    break;
                  case "select":
                    (e =
                      typeof l.is == "string"
                        ? n.createElement("select", { is: l.is })
                        : n.createElement("select")),
                      l.multiple
                        ? (e.multiple = !0)
                        : l.size && (e.size = l.size);
                    break;
                  default:
                    e =
                      typeof l.is == "string"
                        ? n.createElement(a, { is: l.is })
                        : n.createElement(a);
                }
            }
            (e[We] = t), (e[lt] = l);
            e: for (n = t.child; n !== null; ) {
              if (n.tag === 5 || n.tag === 6) e.appendChild(n.stateNode);
              else if (n.tag !== 4 && n.tag !== 27 && n.child !== null) {
                (n.child.return = n), (n = n.child);
                continue;
              }
              if (n === t) break e;
              for (; n.sibling === null; ) {
                if (n.return === null || n.return === t) break e;
                n = n.return;
              }
              (n.sibling.return = n.return), (n = n.sibling);
            }
            t.stateNode = e;
            e: switch ((Fe(e, a, l), a)) {
              case "button":
              case "input":
              case "select":
              case "textarea":
                e = !!l.autoFocus;
                break e;
              case "img":
                e = !0;
                break e;
              default:
                e = !1;
            }
            e && Pt(t);
          }
        }
        return De(t), (t.flags &= -16777217), null;
      case 6:
        if (e && t.stateNode != null) e.memoizedProps !== l && Pt(t);
        else {
          if (typeof l != "string" && t.stateNode === null) throw Error(c(166));
          if (((e = te.current), ln(t))) {
            if (
              ((e = t.stateNode),
              (a = t.memoizedProps),
              (l = null),
              (n = at),
              n !== null)
            )
              switch (n.tag) {
                case 27:
                case 5:
                  l = n.memoizedProps;
              }
            (e[We] = t),
              (e = !!(
                e.nodeValue === a ||
                (l !== null && l.suppressHydrationWarning === !0) ||
                $d(e.nodeValue, a)
              )),
              e || Ya(t);
          } else (e = ai(e).createTextNode(l)), (e[We] = t), (t.stateNode = e);
        }
        return De(t), null;
      case 13:
        if (
          ((l = t.memoizedState),
          e === null ||
            (e.memoizedState !== null && e.memoizedState.dehydrated !== null))
        ) {
          if (((n = ln(t)), l !== null && l.dehydrated !== null)) {
            if (e === null) {
              if (!n) throw Error(c(318));
              if (
                ((n = t.memoizedState),
                (n = n !== null ? n.dehydrated : null),
                !n)
              )
                throw Error(c(317));
              n[We] = t;
            } else
              nn(),
                (t.flags & 128) === 0 && (t.memoizedState = null),
                (t.flags |= 4);
            De(t), (n = !1);
          } else
            (n = Do()),
              e !== null &&
                e.memoizedState !== null &&
                (e.memoizedState.hydrationErrors = n),
              (n = !0);
          if (!n) return t.flags & 256 ? (Ft(t), t) : (Ft(t), null);
        }
        if ((Ft(t), (t.flags & 128) !== 0)) return (t.lanes = a), t;
        if (
          ((a = l !== null), (e = e !== null && e.memoizedState !== null), a)
        ) {
          (l = t.child),
            (n = null),
            l.alternate !== null &&
              l.alternate.memoizedState !== null &&
              l.alternate.memoizedState.cachePool !== null &&
              (n = l.alternate.memoizedState.cachePool.pool);
          var i = null;
          l.memoizedState !== null &&
            l.memoizedState.cachePool !== null &&
            (i = l.memoizedState.cachePool.pool),
            i !== n && (l.flags |= 2048);
        }
        return (
          a !== e && a && (t.child.flags |= 8192),
          Gs(t, t.updateQueue),
          De(t),
          null
        );
      case 4:
        return ia(), e === null && zu(t.stateNode.containerInfo), De(t), null;
      case 10:
        return Jt(t.type), De(t), null;
      case 19:
        if ((J(Ge), (n = t.memoizedState), n === null)) return De(t), null;
        if (((l = (t.flags & 128) !== 0), (i = n.rendering), i === null))
          if (l) jn(n, !1);
          else {
            if (Le !== 0 || (e !== null && (e.flags & 128) !== 0))
              for (e = t.child; e !== null; ) {
                if (((i = Bs(e)), i !== null)) {
                  for (
                    t.flags |= 128,
                      jn(n, !1),
                      e = i.updateQueue,
                      t.updateQueue = e,
                      Gs(t, e),
                      t.subtreeFlags = 0,
                      e = a,
                      a = t.child;
                    a !== null;

                  )
                    Ro(a, e), (a = a.sibling);
                  return K(Ge, (Ge.current & 1) | 2), t.child;
                }
                e = e.sibling;
              }
            n.tail !== null &&
              Dt() > Zs &&
              ((t.flags |= 128), (l = !0), jn(n, !1), (t.lanes = 4194304));
          }
        else {
          if (!l)
            if (((e = Bs(i)), e !== null)) {
              if (
                ((t.flags |= 128),
                (l = !0),
                (e = e.updateQueue),
                (t.updateQueue = e),
                Gs(t, e),
                jn(n, !0),
                n.tail === null &&
                  n.tailMode === "hidden" &&
                  !i.alternate &&
                  !Se)
              )
                return De(t), null;
            } else
              2 * Dt() - n.renderingStartTime > Zs &&
                a !== 536870912 &&
                ((t.flags |= 128), (l = !0), jn(n, !1), (t.lanes = 4194304));
          n.isBackwards
            ? ((i.sibling = t.child), (t.child = i))
            : ((e = n.last),
              e !== null ? (e.sibling = i) : (t.child = i),
              (n.last = i));
        }
        return n.tail !== null
          ? ((t = n.tail),
            (n.rendering = t),
            (n.tail = t.sibling),
            (n.renderingStartTime = Dt()),
            (t.sibling = null),
            (e = Ge.current),
            K(Ge, l ? (e & 1) | 2 : e & 1),
            t)
          : (De(t), null);
      case 22:
      case 23:
        return (
          Ft(t),
          kr(),
          (l = t.memoizedState !== null),
          e !== null
            ? (e.memoizedState !== null) !== l && (t.flags |= 8192)
            : l && (t.flags |= 8192),
          l
            ? (a & 536870912) !== 0 &&
              (t.flags & 128) === 0 &&
              (De(t), t.subtreeFlags & 6 && (t.flags |= 8192))
            : De(t),
          (a = t.updateQueue),
          a !== null && Gs(t, a.retryQueue),
          (a = null),
          e !== null &&
            e.memoizedState !== null &&
            e.memoizedState.cachePool !== null &&
            (a = e.memoizedState.cachePool.pool),
          (l = null),
          t.memoizedState !== null &&
            t.memoizedState.cachePool !== null &&
            (l = t.memoizedState.cachePool.pool),
          l !== a && (t.flags |= 2048),
          e !== null && J(Xa),
          null
        );
      case 24:
        return (
          (a = null),
          e !== null && (a = e.memoizedState.cache),
          t.memoizedState.cache !== a && (t.flags |= 2048),
          Jt(Ve),
          De(t),
          null
        );
      case 25:
        return null;
      case 30:
        return null;
    }
    throw Error(c(156, t.tag));
  }
  function dp(e, t) {
    switch ((Sr(t), t.tag)) {
      case 1:
        return (
          (e = t.flags), e & 65536 ? ((t.flags = (e & -65537) | 128), t) : null
        );
      case 3:
        return (
          Jt(Ve),
          ia(),
          (e = t.flags),
          (e & 65536) !== 0 && (e & 128) === 0
            ? ((t.flags = (e & -65537) | 128), t)
            : null
        );
      case 26:
      case 27:
      case 5:
        return es(t), null;
      case 13:
        if (
          (Ft(t), (e = t.memoizedState), e !== null && e.dehydrated !== null)
        ) {
          if (t.alternate === null) throw Error(c(340));
          nn();
        }
        return (
          (e = t.flags), e & 65536 ? ((t.flags = (e & -65537) | 128), t) : null
        );
      case 19:
        return J(Ge), null;
      case 4:
        return ia(), null;
      case 10:
        return Jt(t.type), null;
      case 22:
      case 23:
        return (
          Ft(t),
          kr(),
          e !== null && J(Xa),
          (e = t.flags),
          e & 65536 ? ((t.flags = (e & -65537) | 128), t) : null
        );
      case 24:
        return Jt(Ve), null;
      case 25:
        return null;
      default:
        return null;
    }
  }
  function ld(e, t) {
    switch ((Sr(t), t.tag)) {
      case 3:
        Jt(Ve), ia();
        break;
      case 26:
      case 27:
      case 5:
        es(t);
        break;
      case 4:
        ia();
        break;
      case 13:
        Ft(t);
        break;
      case 19:
        J(Ge);
        break;
      case 10:
        Jt(t.type);
        break;
      case 22:
      case 23:
        Ft(t), kr(), e !== null && J(Xa);
        break;
      case 24:
        Jt(Ve);
    }
  }
  function Sn(e, t) {
    try {
      var a = t.updateQueue,
        l = a !== null ? a.lastEffect : null;
      if (l !== null) {
        var n = l.next;
        a = n;
        do {
          if ((a.tag & e) === e) {
            l = void 0;
            var i = a.create,
              d = a.inst;
            (l = i()), (d.destroy = l);
          }
          a = a.next;
        } while (a !== n);
      }
    } catch (h) {
      Ae(t, t.return, h);
    }
  }
  function ga(e, t, a) {
    try {
      var l = t.updateQueue,
        n = l !== null ? l.lastEffect : null;
      if (n !== null) {
        var i = n.next;
        l = i;
        do {
          if ((l.tag & e) === e) {
            var d = l.inst,
              h = d.destroy;
            if (h !== void 0) {
              (d.destroy = void 0), (n = t);
              var j = a,
                O = h;
              try {
                O();
              } catch (V) {
                Ae(n, j, V);
              }
            }
          }
          l = l.next;
        } while (l !== i);
      }
    } catch (V) {
      Ae(t, t.return, V);
    }
  }
  function nd(e) {
    var t = e.updateQueue;
    if (t !== null) {
      var a = e.stateNode;
      try {
        Xo(t, a);
      } catch (l) {
        Ae(e, e.return, l);
      }
    }
  }
  function sd(e, t, a) {
    (a.props = Za(e.type, e.memoizedProps)), (a.state = e.memoizedState);
    try {
      a.componentWillUnmount();
    } catch (l) {
      Ae(e, t, l);
    }
  }
  function Nn(e, t) {
    try {
      var a = e.ref;
      if (a !== null) {
        switch (e.tag) {
          case 26:
          case 27:
          case 5:
            var l = e.stateNode;
            break;
          case 30:
            l = e.stateNode;
            break;
          default:
            l = e.stateNode;
        }
        typeof a == "function" ? (e.refCleanup = a(l)) : (a.current = l);
      }
    } catch (n) {
      Ae(e, t, n);
    }
  }
  function kt(e, t) {
    var a = e.ref,
      l = e.refCleanup;
    if (a !== null)
      if (typeof l == "function")
        try {
          l();
        } catch (n) {
          Ae(e, t, n);
        } finally {
          (e.refCleanup = null),
            (e = e.alternate),
            e != null && (e.refCleanup = null);
        }
      else if (typeof a == "function")
        try {
          a(null);
        } catch (n) {
          Ae(e, t, n);
        }
      else a.current = null;
  }
  function id(e) {
    var t = e.type,
      a = e.memoizedProps,
      l = e.stateNode;
    try {
      e: switch (t) {
        case "button":
        case "input":
        case "select":
        case "textarea":
          a.autoFocus && l.focus();
          break e;
        case "img":
          a.src ? (l.src = a.src) : a.srcSet && (l.srcset = a.srcSet);
      }
    } catch (n) {
      Ae(e, e.return, n);
    }
  }
  function ou(e, t, a) {
    try {
      var l = e.stateNode;
      Dp(l, e.type, a, t), (l[lt] = t);
    } catch (n) {
      Ae(e, e.return, n);
    }
  }
  function rd(e) {
    return (
      e.tag === 5 ||
      e.tag === 3 ||
      e.tag === 26 ||
      (e.tag === 27 && Ea(e.type)) ||
      e.tag === 4
    );
  }
  function fu(e) {
    e: for (;;) {
      for (; e.sibling === null; ) {
        if (e.return === null || rd(e.return)) return null;
        e = e.return;
      }
      for (
        e.sibling.return = e.return, e = e.sibling;
        e.tag !== 5 && e.tag !== 6 && e.tag !== 18;

      ) {
        if (
          (e.tag === 27 && Ea(e.type)) ||
          e.flags & 2 ||
          e.child === null ||
          e.tag === 4
        )
          continue e;
        (e.child.return = e), (e = e.child);
      }
      if (!(e.flags & 2)) return e.stateNode;
    }
  }
  function du(e, t, a) {
    var l = e.tag;
    if (l === 5 || l === 6)
      (e = e.stateNode),
        t
          ? (a.nodeType === 9
              ? a.body
              : a.nodeName === "HTML"
              ? a.ownerDocument.body
              : a
            ).insertBefore(e, t)
          : ((t =
              a.nodeType === 9
                ? a.body
                : a.nodeName === "HTML"
                ? a.ownerDocument.body
                : a),
            t.appendChild(e),
            (a = a._reactRootContainer),
            a != null || t.onclick !== null || (t.onclick = ti));
    else if (
      l !== 4 &&
      (l === 27 && Ea(e.type) && ((a = e.stateNode), (t = null)),
      (e = e.child),
      e !== null)
    )
      for (du(e, t, a), e = e.sibling; e !== null; )
        du(e, t, a), (e = e.sibling);
  }
  function Xs(e, t, a) {
    var l = e.tag;
    if (l === 5 || l === 6)
      (e = e.stateNode), t ? a.insertBefore(e, t) : a.appendChild(e);
    else if (
      l !== 4 &&
      (l === 27 && Ea(e.type) && (a = e.stateNode), (e = e.child), e !== null)
    )
      for (Xs(e, t, a), e = e.sibling; e !== null; )
        Xs(e, t, a), (e = e.sibling);
  }
  function ud(e) {
    var t = e.stateNode,
      a = e.memoizedProps;
    try {
      for (var l = e.type, n = t.attributes; n.length; )
        t.removeAttributeNode(n[0]);
      Fe(t, l, a), (t[We] = e), (t[lt] = a);
    } catch (i) {
      Ae(e, e.return, i);
    }
  }
  var It = !1,
    He = !1,
    mu = !1,
    cd = typeof WeakSet == "function" ? WeakSet : Set,
    Ze = null;
  function mp(e, t) {
    if (((e = e.containerInfo), (Lu = ui), (e = bo(e)), fr(e))) {
      if ("selectionStart" in e)
        var a = { start: e.selectionStart, end: e.selectionEnd };
      else
        e: {
          a = ((a = e.ownerDocument) && a.defaultView) || window;
          var l = a.getSelection && a.getSelection();
          if (l && l.rangeCount !== 0) {
            a = l.anchorNode;
            var n = l.anchorOffset,
              i = l.focusNode;
            l = l.focusOffset;
            try {
              a.nodeType, i.nodeType;
            } catch {
              a = null;
              break e;
            }
            var d = 0,
              h = -1,
              j = -1,
              O = 0,
              V = 0,
              X = e,
              z = null;
            t: for (;;) {
              for (
                var k;
                X !== a || (n !== 0 && X.nodeType !== 3) || (h = d + n),
                  X !== i || (l !== 0 && X.nodeType !== 3) || (j = d + l),
                  X.nodeType === 3 && (d += X.nodeValue.length),
                  (k = X.firstChild) !== null;

              )
                (z = X), (X = k);
              for (;;) {
                if (X === e) break t;
                if (
                  (z === a && ++O === n && (h = d),
                  z === i && ++V === l && (j = d),
                  (k = X.nextSibling) !== null)
                )
                  break;
                (X = z), (z = X.parentNode);
              }
              X = k;
            }
            a = h === -1 || j === -1 ? null : { start: h, end: j };
          } else a = null;
        }
      a = a || { start: 0, end: 0 };
    } else a = null;
    for (
      Bu = { focusedElem: e, selectionRange: a }, ui = !1, Ze = t;
      Ze !== null;

    )
      if (
        ((t = Ze), (e = t.child), (t.subtreeFlags & 1024) !== 0 && e !== null)
      )
        (e.return = t), (Ze = e);
      else
        for (; Ze !== null; ) {
          switch (((t = Ze), (i = t.alternate), (e = t.flags), t.tag)) {
            case 0:
              break;
            case 11:
            case 15:
              break;
            case 1:
              if ((e & 1024) !== 0 && i !== null) {
                (e = void 0),
                  (a = t),
                  (n = i.memoizedProps),
                  (i = i.memoizedState),
                  (l = a.stateNode);
                try {
                  var ie = Za(a.type, n, a.elementType === a.type);
                  (e = l.getSnapshotBeforeUpdate(ie, i)),
                    (l.__reactInternalSnapshotBeforeUpdate = e);
                } catch (ne) {
                  Ae(a, a.return, ne);
                }
              }
              break;
            case 3:
              if ((e & 1024) !== 0) {
                if (
                  ((e = t.stateNode.containerInfo), (a = e.nodeType), a === 9)
                )
                  Yu(e);
                else if (a === 1)
                  switch (e.nodeName) {
                    case "HEAD":
                    case "HTML":
                    case "BODY":
                      Yu(e);
                      break;
                    default:
                      e.textContent = "";
                  }
              }
              break;
            case 5:
            case 26:
            case 27:
            case 6:
            case 4:
            case 17:
              break;
            default:
              if ((e & 1024) !== 0) throw Error(c(163));
          }
          if (((e = t.sibling), e !== null)) {
            (e.return = t.return), (Ze = e);
            break;
          }
          Ze = t.return;
        }
  }
  function od(e, t, a) {
    var l = a.flags;
    switch (a.tag) {
      case 0:
      case 11:
      case 15:
        ya(e, a), l & 4 && Sn(5, a);
        break;
      case 1:
        if ((ya(e, a), l & 4))
          if (((e = a.stateNode), t === null))
            try {
              e.componentDidMount();
            } catch (d) {
              Ae(a, a.return, d);
            }
          else {
            var n = Za(a.type, t.memoizedProps);
            t = t.memoizedState;
            try {
              e.componentDidUpdate(n, t, e.__reactInternalSnapshotBeforeUpdate);
            } catch (d) {
              Ae(a, a.return, d);
            }
          }
        l & 64 && nd(a), l & 512 && Nn(a, a.return);
        break;
      case 3:
        if ((ya(e, a), l & 64 && ((e = a.updateQueue), e !== null))) {
          if (((t = null), a.child !== null))
            switch (a.child.tag) {
              case 27:
              case 5:
                t = a.child.stateNode;
                break;
              case 1:
                t = a.child.stateNode;
            }
          try {
            Xo(e, t);
          } catch (d) {
            Ae(a, a.return, d);
          }
        }
        break;
      case 27:
        t === null && l & 4 && ud(a);
      case 26:
      case 5:
        ya(e, a), t === null && l & 4 && id(a), l & 512 && Nn(a, a.return);
        break;
      case 12:
        ya(e, a);
        break;
      case 13:
        ya(e, a),
          l & 4 && md(e, a),
          l & 64 &&
            ((e = a.memoizedState),
            e !== null &&
              ((e = e.dehydrated),
              e !== null && ((a = Sp.bind(null, a)), qp(e, a))));
        break;
      case 22:
        if (((l = a.memoizedState !== null || It), !l)) {
          (t = (t !== null && t.memoizedState !== null) || He), (n = It);
          var i = He;
          (It = l),
            (He = t) && !i ? ba(e, a, (a.subtreeFlags & 8772) !== 0) : ya(e, a),
            (It = n),
            (He = i);
        }
        break;
      case 30:
        break;
      default:
        ya(e, a);
    }
  }
  function fd(e) {
    var t = e.alternate;
    t !== null && ((e.alternate = null), fd(t)),
      (e.child = null),
      (e.deletions = null),
      (e.sibling = null),
      e.tag === 5 && ((t = e.stateNode), t !== null && Zi(t)),
      (e.stateNode = null),
      (e.return = null),
      (e.dependencies = null),
      (e.memoizedProps = null),
      (e.memoizedState = null),
      (e.pendingProps = null),
      (e.stateNode = null),
      (e.updateQueue = null);
  }
  var Oe = null,
    it = !1;
  function ea(e, t, a) {
    for (a = a.child; a !== null; ) dd(e, t, a), (a = a.sibling);
  }
  function dd(e, t, a) {
    if (ot && typeof ot.onCommitFiberUnmount == "function")
      try {
        ot.onCommitFiberUnmount(Gl, a);
      } catch {}
    switch (a.tag) {
      case 26:
        He || kt(a, t),
          ea(e, t, a),
          a.memoizedState
            ? a.memoizedState.count--
            : a.stateNode && ((a = a.stateNode), a.parentNode.removeChild(a));
        break;
      case 27:
        He || kt(a, t);
        var l = Oe,
          n = it;
        Ea(a.type) && ((Oe = a.stateNode), (it = !1)),
          ea(e, t, a),
          Mn(a.stateNode),
          (Oe = l),
          (it = n);
        break;
      case 5:
        He || kt(a, t);
      case 6:
        if (
          ((l = Oe),
          (n = it),
          (Oe = null),
          ea(e, t, a),
          (Oe = l),
          (it = n),
          Oe !== null)
        )
          if (it)
            try {
              (Oe.nodeType === 9
                ? Oe.body
                : Oe.nodeName === "HTML"
                ? Oe.ownerDocument.body
                : Oe
              ).removeChild(a.stateNode);
            } catch (i) {
              Ae(a, t, i);
            }
          else
            try {
              Oe.removeChild(a.stateNode);
            } catch (i) {
              Ae(a, t, i);
            }
        break;
      case 18:
        Oe !== null &&
          (it
            ? ((e = Oe),
              em(
                e.nodeType === 9
                  ? e.body
                  : e.nodeName === "HTML"
                  ? e.ownerDocument.body
                  : e,
                a.stateNode
              ),
              qn(e))
            : em(Oe, a.stateNode));
        break;
      case 4:
        (l = Oe),
          (n = it),
          (Oe = a.stateNode.containerInfo),
          (it = !0),
          ea(e, t, a),
          (Oe = l),
          (it = n);
        break;
      case 0:
      case 11:
      case 14:
      case 15:
        He || ga(2, a, t), He || ga(4, a, t), ea(e, t, a);
        break;
      case 1:
        He ||
          (kt(a, t),
          (l = a.stateNode),
          typeof l.componentWillUnmount == "function" && sd(a, t, l)),
          ea(e, t, a);
        break;
      case 21:
        ea(e, t, a);
        break;
      case 22:
        (He = (l = He) || a.memoizedState !== null), ea(e, t, a), (He = l);
        break;
      default:
        ea(e, t, a);
    }
  }
  function md(e, t) {
    if (
      t.memoizedState === null &&
      ((e = t.alternate),
      e !== null &&
        ((e = e.memoizedState), e !== null && ((e = e.dehydrated), e !== null)))
    )
      try {
        qn(e);
      } catch (a) {
        Ae(t, t.return, a);
      }
  }
  function hp(e) {
    switch (e.tag) {
      case 13:
      case 19:
        var t = e.stateNode;
        return t === null && (t = e.stateNode = new cd()), t;
      case 22:
        return (
          (e = e.stateNode),
          (t = e._retryCache),
          t === null && (t = e._retryCache = new cd()),
          t
        );
      default:
        throw Error(c(435, e.tag));
    }
  }
  function hu(e, t) {
    var a = hp(e);
    t.forEach(function (l) {
      var n = Np.bind(null, e, l);
      a.has(l) || (a.add(l), l.then(n, n));
    });
  }
  function ht(e, t) {
    var a = t.deletions;
    if (a !== null)
      for (var l = 0; l < a.length; l++) {
        var n = a[l],
          i = e,
          d = t,
          h = d;
        e: for (; h !== null; ) {
          switch (h.tag) {
            case 27:
              if (Ea(h.type)) {
                (Oe = h.stateNode), (it = !1);
                break e;
              }
              break;
            case 5:
              (Oe = h.stateNode), (it = !1);
              break e;
            case 3:
            case 4:
              (Oe = h.stateNode.containerInfo), (it = !0);
              break e;
          }
          h = h.return;
        }
        if (Oe === null) throw Error(c(160));
        dd(i, d, n),
          (Oe = null),
          (it = !1),
          (i = n.alternate),
          i !== null && (i.return = null),
          (n.return = null);
      }
    if (t.subtreeFlags & 13878)
      for (t = t.child; t !== null; ) hd(t, e), (t = t.sibling);
  }
  var Ct = null;
  function hd(e, t) {
    var a = e.alternate,
      l = e.flags;
    switch (e.tag) {
      case 0:
      case 11:
      case 14:
      case 15:
        ht(t, e),
          pt(e),
          l & 4 && (ga(3, e, e.return), Sn(3, e), ga(5, e, e.return));
        break;
      case 1:
        ht(t, e),
          pt(e),
          l & 512 && (He || a === null || kt(a, a.return)),
          l & 64 &&
            It &&
            ((e = e.updateQueue),
            e !== null &&
              ((l = e.callbacks),
              l !== null &&
                ((a = e.shared.hiddenCallbacks),
                (e.shared.hiddenCallbacks = a === null ? l : a.concat(l)))));
        break;
      case 26:
        var n = Ct;
        if (
          (ht(t, e),
          pt(e),
          l & 512 && (He || a === null || kt(a, a.return)),
          l & 4)
        ) {
          var i = a !== null ? a.memoizedState : null;
          if (((l = e.memoizedState), a === null))
            if (l === null)
              if (e.stateNode === null) {
                e: {
                  (l = e.type),
                    (a = e.memoizedProps),
                    (n = n.ownerDocument || n);
                  t: switch (l) {
                    case "title":
                      (i = n.getElementsByTagName("title")[0]),
                        (!i ||
                          i[Zl] ||
                          i[We] ||
                          i.namespaceURI === "http://www.w3.org/2000/svg" ||
                          i.hasAttribute("itemprop")) &&
                          ((i = n.createElement(l)),
                          n.head.insertBefore(
                            i,
                            n.querySelector("head > title")
                          )),
                        Fe(i, l, a),
                        (i[We] = e),
                        Xe(i),
                        (l = i);
                      break e;
                    case "link":
                      var d = um("link", "href", n).get(l + (a.href || ""));
                      if (d) {
                        for (var h = 0; h < d.length; h++)
                          if (
                            ((i = d[h]),
                            i.getAttribute("href") ===
                              (a.href == null || a.href === ""
                                ? null
                                : a.href) &&
                              i.getAttribute("rel") ===
                                (a.rel == null ? null : a.rel) &&
                              i.getAttribute("title") ===
                                (a.title == null ? null : a.title) &&
                              i.getAttribute("crossorigin") ===
                                (a.crossOrigin == null ? null : a.crossOrigin))
                          ) {
                            d.splice(h, 1);
                            break t;
                          }
                      }
                      (i = n.createElement(l)),
                        Fe(i, l, a),
                        n.head.appendChild(i);
                      break;
                    case "meta":
                      if (
                        (d = um("meta", "content", n).get(
                          l + (a.content || "")
                        ))
                      ) {
                        for (h = 0; h < d.length; h++)
                          if (
                            ((i = d[h]),
                            i.getAttribute("content") ===
                              (a.content == null ? null : "" + a.content) &&
                              i.getAttribute("name") ===
                                (a.name == null ? null : a.name) &&
                              i.getAttribute("property") ===
                                (a.property == null ? null : a.property) &&
                              i.getAttribute("http-equiv") ===
                                (a.httpEquiv == null ? null : a.httpEquiv) &&
                              i.getAttribute("charset") ===
                                (a.charSet == null ? null : a.charSet))
                          ) {
                            d.splice(h, 1);
                            break t;
                          }
                      }
                      (i = n.createElement(l)),
                        Fe(i, l, a),
                        n.head.appendChild(i);
                      break;
                    default:
                      throw Error(c(468, l));
                  }
                  (i[We] = e), Xe(i), (l = i);
                }
                e.stateNode = l;
              } else cm(n, e.type, e.stateNode);
            else e.stateNode = rm(n, l, e.memoizedProps);
          else
            i !== l
              ? (i === null
                  ? a.stateNode !== null &&
                    ((a = a.stateNode), a.parentNode.removeChild(a))
                  : i.count--,
                l === null
                  ? cm(n, e.type, e.stateNode)
                  : rm(n, l, e.memoizedProps))
              : l === null &&
                e.stateNode !== null &&
                ou(e, e.memoizedProps, a.memoizedProps);
        }
        break;
      case 27:
        ht(t, e),
          pt(e),
          l & 512 && (He || a === null || kt(a, a.return)),
          a !== null && l & 4 && ou(e, e.memoizedProps, a.memoizedProps);
        break;
      case 5:
        if (
          (ht(t, e),
          pt(e),
          l & 512 && (He || a === null || kt(a, a.return)),
          e.flags & 32)
        ) {
          n = e.stateNode;
          try {
            rl(n, "");
          } catch (k) {
            Ae(e, e.return, k);
          }
        }
        l & 4 &&
          e.stateNode != null &&
          ((n = e.memoizedProps), ou(e, n, a !== null ? a.memoizedProps : n)),
          l & 1024 && (mu = !0);
        break;
      case 6:
        if ((ht(t, e), pt(e), l & 4)) {
          if (e.stateNode === null) throw Error(c(162));
          (l = e.memoizedProps), (a = e.stateNode);
          try {
            a.nodeValue = l;
          } catch (k) {
            Ae(e, e.return, k);
          }
        }
        break;
      case 3:
        if (
          ((si = null),
          (n = Ct),
          (Ct = li(t.containerInfo)),
          ht(t, e),
          (Ct = n),
          pt(e),
          l & 4 && a !== null && a.memoizedState.isDehydrated)
        )
          try {
            qn(t.containerInfo);
          } catch (k) {
            Ae(e, e.return, k);
          }
        mu && ((mu = !1), pd(e));
        break;
      case 4:
        (l = Ct),
          (Ct = li(e.stateNode.containerInfo)),
          ht(t, e),
          pt(e),
          (Ct = l);
        break;
      case 12:
        ht(t, e), pt(e);
        break;
      case 13:
        ht(t, e),
          pt(e),
          e.child.flags & 8192 &&
            (e.memoizedState !== null) !=
              (a !== null && a.memoizedState !== null) &&
            (vu = Dt()),
          l & 4 &&
            ((l = e.updateQueue),
            l !== null && ((e.updateQueue = null), hu(e, l)));
        break;
      case 22:
        n = e.memoizedState !== null;
        var j = a !== null && a.memoizedState !== null,
          O = It,
          V = He;
        if (
          ((It = O || n),
          (He = V || j),
          ht(t, e),
          (He = V),
          (It = O),
          pt(e),
          l & 8192)
        )
          e: for (
            t = e.stateNode,
              t._visibility = n ? t._visibility & -2 : t._visibility | 1,
              n && (a === null || j || It || He || Ka(e)),
              a = null,
              t = e;
            ;

          ) {
            if (t.tag === 5 || t.tag === 26) {
              if (a === null) {
                j = a = t;
                try {
                  if (((i = j.stateNode), n))
                    (d = i.style),
                      typeof d.setProperty == "function"
                        ? d.setProperty("display", "none", "important")
                        : (d.display = "none");
                  else {
                    h = j.stateNode;
                    var X = j.memoizedProps.style,
                      z =
                        X != null && X.hasOwnProperty("display")
                          ? X.display
                          : null;
                    h.style.display =
                      z == null || typeof z == "boolean" ? "" : ("" + z).trim();
                  }
                } catch (k) {
                  Ae(j, j.return, k);
                }
              }
            } else if (t.tag === 6) {
              if (a === null) {
                j = t;
                try {
                  j.stateNode.nodeValue = n ? "" : j.memoizedProps;
                } catch (k) {
                  Ae(j, j.return, k);
                }
              }
            } else if (
              ((t.tag !== 22 && t.tag !== 23) ||
                t.memoizedState === null ||
                t === e) &&
              t.child !== null
            ) {
              (t.child.return = t), (t = t.child);
              continue;
            }
            if (t === e) break e;
            for (; t.sibling === null; ) {
              if (t.return === null || t.return === e) break e;
              a === t && (a = null), (t = t.return);
            }
            a === t && (a = null),
              (t.sibling.return = t.return),
              (t = t.sibling);
          }
        l & 4 &&
          ((l = e.updateQueue),
          l !== null &&
            ((a = l.retryQueue),
            a !== null && ((l.retryQueue = null), hu(e, a))));
        break;
      case 19:
        ht(t, e),
          pt(e),
          l & 4 &&
            ((l = e.updateQueue),
            l !== null && ((e.updateQueue = null), hu(e, l)));
        break;
      case 30:
        break;
      case 21:
        break;
      default:
        ht(t, e), pt(e);
    }
  }
  function pt(e) {
    var t = e.flags;
    if (t & 2) {
      try {
        for (var a, l = e.return; l !== null; ) {
          if (rd(l)) {
            a = l;
            break;
          }
          l = l.return;
        }
        if (a == null) throw Error(c(160));
        switch (a.tag) {
          case 27:
            var n = a.stateNode,
              i = fu(e);
            Xs(e, i, n);
            break;
          case 5:
            var d = a.stateNode;
            a.flags & 32 && (rl(d, ""), (a.flags &= -33));
            var h = fu(e);
            Xs(e, h, d);
            break;
          case 3:
          case 4:
            var j = a.stateNode.containerInfo,
              O = fu(e);
            du(e, O, j);
            break;
          default:
            throw Error(c(161));
        }
      } catch (V) {
        Ae(e, e.return, V);
      }
      e.flags &= -3;
    }
    t & 4096 && (e.flags &= -4097);
  }
  function pd(e) {
    if (e.subtreeFlags & 1024)
      for (e = e.child; e !== null; ) {
        var t = e;
        pd(t),
          t.tag === 5 && t.flags & 1024 && t.stateNode.reset(),
          (e = e.sibling);
      }
  }
  function ya(e, t) {
    if (t.subtreeFlags & 8772)
      for (t = t.child; t !== null; ) od(e, t.alternate, t), (t = t.sibling);
  }
  function Ka(e) {
    for (e = e.child; e !== null; ) {
      var t = e;
      switch (t.tag) {
        case 0:
        case 11:
        case 14:
        case 15:
          ga(4, t, t.return), Ka(t);
          break;
        case 1:
          kt(t, t.return);
          var a = t.stateNode;
          typeof a.componentWillUnmount == "function" && sd(t, t.return, a),
            Ka(t);
          break;
        case 27:
          Mn(t.stateNode);
        case 26:
        case 5:
          kt(t, t.return), Ka(t);
          break;
        case 22:
          t.memoizedState === null && Ka(t);
          break;
        case 30:
          Ka(t);
          break;
        default:
          Ka(t);
      }
      e = e.sibling;
    }
  }
  function ba(e, t, a) {
    for (a = a && (t.subtreeFlags & 8772) !== 0, t = t.child; t !== null; ) {
      var l = t.alternate,
        n = e,
        i = t,
        d = i.flags;
      switch (i.tag) {
        case 0:
        case 11:
        case 15:
          ba(n, i, a), Sn(4, i);
          break;
        case 1:
          if (
            (ba(n, i, a),
            (l = i),
            (n = l.stateNode),
            typeof n.componentDidMount == "function")
          )
            try {
              n.componentDidMount();
            } catch (O) {
              Ae(l, l.return, O);
            }
          if (((l = i), (n = l.updateQueue), n !== null)) {
            var h = l.stateNode;
            try {
              var j = n.shared.hiddenCallbacks;
              if (j !== null)
                for (n.shared.hiddenCallbacks = null, n = 0; n < j.length; n++)
                  Go(j[n], h);
            } catch (O) {
              Ae(l, l.return, O);
            }
          }
          a && d & 64 && nd(i), Nn(i, i.return);
          break;
        case 27:
          ud(i);
        case 26:
        case 5:
          ba(n, i, a), a && l === null && d & 4 && id(i), Nn(i, i.return);
          break;
        case 12:
          ba(n, i, a);
          break;
        case 13:
          ba(n, i, a), a && d & 4 && md(n, i);
          break;
        case 22:
          i.memoizedState === null && ba(n, i, a), Nn(i, i.return);
          break;
        case 30:
          break;
        default:
          ba(n, i, a);
      }
      t = t.sibling;
    }
  }
  function pu(e, t) {
    var a = null;
    e !== null &&
      e.memoizedState !== null &&
      e.memoizedState.cachePool !== null &&
      (a = e.memoizedState.cachePool.pool),
      (e = null),
      t.memoizedState !== null &&
        t.memoizedState.cachePool !== null &&
        (e = t.memoizedState.cachePool.pool),
      e !== a && (e != null && e.refCount++, a != null && un(a));
  }
  function xu(e, t) {
    (e = null),
      t.alternate !== null && (e = t.alternate.memoizedState.cache),
      (t = t.memoizedState.cache),
      t !== e && (t.refCount++, e != null && un(e));
  }
  function Lt(e, t, a, l) {
    if (t.subtreeFlags & 10256)
      for (t = t.child; t !== null; ) xd(e, t, a, l), (t = t.sibling);
  }
  function xd(e, t, a, l) {
    var n = t.flags;
    switch (t.tag) {
      case 0:
      case 11:
      case 15:
        Lt(e, t, a, l), n & 2048 && Sn(9, t);
        break;
      case 1:
        Lt(e, t, a, l);
        break;
      case 3:
        Lt(e, t, a, l),
          n & 2048 &&
            ((e = null),
            t.alternate !== null && (e = t.alternate.memoizedState.cache),
            (t = t.memoizedState.cache),
            t !== e && (t.refCount++, e != null && un(e)));
        break;
      case 12:
        if (n & 2048) {
          Lt(e, t, a, l), (e = t.stateNode);
          try {
            var i = t.memoizedProps,
              d = i.id,
              h = i.onPostCommit;
            typeof h == "function" &&
              h(
                d,
                t.alternate === null ? "mount" : "update",
                e.passiveEffectDuration,
                -0
              );
          } catch (j) {
            Ae(t, t.return, j);
          }
        } else Lt(e, t, a, l);
        break;
      case 13:
        Lt(e, t, a, l);
        break;
      case 23:
        break;
      case 22:
        (i = t.stateNode),
          (d = t.alternate),
          t.memoizedState !== null
            ? i._visibility & 2
              ? Lt(e, t, a, l)
              : wn(e, t)
            : i._visibility & 2
            ? Lt(e, t, a, l)
            : ((i._visibility |= 2),
              Tl(e, t, a, l, (t.subtreeFlags & 10256) !== 0)),
          n & 2048 && pu(d, t);
        break;
      case 24:
        Lt(e, t, a, l), n & 2048 && xu(t.alternate, t);
        break;
      default:
        Lt(e, t, a, l);
    }
  }
  function Tl(e, t, a, l, n) {
    for (n = n && (t.subtreeFlags & 10256) !== 0, t = t.child; t !== null; ) {
      var i = e,
        d = t,
        h = a,
        j = l,
        O = d.flags;
      switch (d.tag) {
        case 0:
        case 11:
        case 15:
          Tl(i, d, h, j, n), Sn(8, d);
          break;
        case 23:
          break;
        case 22:
          var V = d.stateNode;
          d.memoizedState !== null
            ? V._visibility & 2
              ? Tl(i, d, h, j, n)
              : wn(i, d)
            : ((V._visibility |= 2), Tl(i, d, h, j, n)),
            n && O & 2048 && pu(d.alternate, d);
          break;
        case 24:
          Tl(i, d, h, j, n), n && O & 2048 && xu(d.alternate, d);
          break;
        default:
          Tl(i, d, h, j, n);
      }
      t = t.sibling;
    }
  }
  function wn(e, t) {
    if (t.subtreeFlags & 10256)
      for (t = t.child; t !== null; ) {
        var a = e,
          l = t,
          n = l.flags;
        switch (l.tag) {
          case 22:
            wn(a, l), n & 2048 && pu(l.alternate, l);
            break;
          case 24:
            wn(a, l), n & 2048 && xu(l.alternate, l);
            break;
          default:
            wn(a, l);
        }
        t = t.sibling;
      }
  }
  var En = 8192;
  function _l(e) {
    if (e.subtreeFlags & En)
      for (e = e.child; e !== null; ) gd(e), (e = e.sibling);
  }
  function gd(e) {
    switch (e.tag) {
      case 26:
        _l(e),
          e.flags & En &&
            e.memoizedState !== null &&
            Ip(Ct, e.memoizedState, e.memoizedProps);
        break;
      case 5:
        _l(e);
        break;
      case 3:
      case 4:
        var t = Ct;
        (Ct = li(e.stateNode.containerInfo)), _l(e), (Ct = t);
        break;
      case 22:
        e.memoizedState === null &&
          ((t = e.alternate),
          t !== null && t.memoizedState !== null
            ? ((t = En), (En = 16777216), _l(e), (En = t))
            : _l(e));
        break;
      default:
        _l(e);
    }
  }
  function yd(e) {
    var t = e.alternate;
    if (t !== null && ((e = t.child), e !== null)) {
      t.child = null;
      do (t = e.sibling), (e.sibling = null), (e = t);
      while (e !== null);
    }
  }
  function Tn(e) {
    var t = e.deletions;
    if ((e.flags & 16) !== 0) {
      if (t !== null)
        for (var a = 0; a < t.length; a++) {
          var l = t[a];
          (Ze = l), vd(l, e);
        }
      yd(e);
    }
    if (e.subtreeFlags & 10256)
      for (e = e.child; e !== null; ) bd(e), (e = e.sibling);
  }
  function bd(e) {
    switch (e.tag) {
      case 0:
      case 11:
      case 15:
        Tn(e), e.flags & 2048 && ga(9, e, e.return);
        break;
      case 3:
        Tn(e);
        break;
      case 12:
        Tn(e);
        break;
      case 22:
        var t = e.stateNode;
        e.memoizedState !== null &&
        t._visibility & 2 &&
        (e.return === null || e.return.tag !== 13)
          ? ((t._visibility &= -3), Qs(e))
          : Tn(e);
        break;
      default:
        Tn(e);
    }
  }
  function Qs(e) {
    var t = e.deletions;
    if ((e.flags & 16) !== 0) {
      if (t !== null)
        for (var a = 0; a < t.length; a++) {
          var l = t[a];
          (Ze = l), vd(l, e);
        }
      yd(e);
    }
    for (e = e.child; e !== null; ) {
      switch (((t = e), t.tag)) {
        case 0:
        case 11:
        case 15:
          ga(8, t, t.return), Qs(t);
          break;
        case 22:
          (a = t.stateNode),
            a._visibility & 2 && ((a._visibility &= -3), Qs(t));
          break;
        default:
          Qs(t);
      }
      e = e.sibling;
    }
  }
  function vd(e, t) {
    for (; Ze !== null; ) {
      var a = Ze;
      switch (a.tag) {
        case 0:
        case 11:
        case 15:
          ga(8, a, t);
          break;
        case 23:
        case 22:
          if (a.memoizedState !== null && a.memoizedState.cachePool !== null) {
            var l = a.memoizedState.cachePool.pool;
            l != null && l.refCount++;
          }
          break;
        case 24:
          un(a.memoizedState.cache);
      }
      if (((l = a.child), l !== null)) (l.return = a), (Ze = l);
      else
        e: for (a = e; Ze !== null; ) {
          l = Ze;
          var n = l.sibling,
            i = l.return;
          if ((fd(l), l === a)) {
            Ze = null;
            break e;
          }
          if (n !== null) {
            (n.return = i), (Ze = n);
            break e;
          }
          Ze = i;
        }
    }
  }
  var pp = {
      getCacheForType: function (e) {
        var t = Pe(Ve),
          a = t.data.get(e);
        return a === void 0 && ((a = e()), t.data.set(e, a)), a;
      },
    },
    xp = typeof WeakMap == "function" ? WeakMap : Map,
    Ne = 0,
    Ce = null,
    xe = null,
    ye = 0,
    we = 0,
    xt = null,
    va = !1,
    Al = !1,
    gu = !1,
    ta = 0,
    Le = 0,
    ja = 0,
    Ja = 0,
    yu = 0,
    _t = 0,
    Rl = 0,
    _n = null,
    rt = null,
    bu = !1,
    vu = 0,
    Zs = 1 / 0,
    Ks = null,
    Sa = null,
    $e = 0,
    Na = null,
    Cl = null,
    Ol = 0,
    ju = 0,
    Su = null,
    jd = null,
    An = 0,
    Nu = null;
  function gt() {
    if ((Ne & 2) !== 0 && ye !== 0) return ye & -ye;
    if (S.T !== null) {
      var e = yl;
      return e !== 0 ? e : Cu();
    }
    return Uc();
  }
  function Sd() {
    _t === 0 && (_t = (ye & 536870912) === 0 || Se ? Oc() : 536870912);
    var e = Tt.current;
    return e !== null && (e.flags |= 32), _t;
  }
  function yt(e, t, a) {
    ((e === Ce && (we === 2 || we === 9)) || e.cancelPendingCommit !== null) &&
      (Ml(e, 0), wa(e, ye, _t, !1)),
      Ql(e, a),
      ((Ne & 2) === 0 || e !== Ce) &&
        (e === Ce &&
          ((Ne & 2) === 0 && (Ja |= a), Le === 4 && wa(e, ye, _t, !1)),
        Bt(e));
  }
  function Nd(e, t, a) {
    if ((Ne & 6) !== 0) throw Error(c(327));
    var l = (!a && (t & 124) === 0 && (t & e.expiredLanes) === 0) || Xl(e, t),
      n = l ? bp(e, t) : Tu(e, t, !0),
      i = l;
    do {
      if (n === 0) {
        Al && !l && wa(e, t, 0, !1);
        break;
      } else {
        if (((a = e.current.alternate), i && !gp(a))) {
          (n = Tu(e, t, !1)), (i = !1);
          continue;
        }
        if (n === 2) {
          if (((i = t), e.errorRecoveryDisabledLanes & i)) var d = 0;
          else
            (d = e.pendingLanes & -536870913),
              (d = d !== 0 ? d : d & 536870912 ? 536870912 : 0);
          if (d !== 0) {
            t = d;
            e: {
              var h = e;
              n = _n;
              var j = h.current.memoizedState.isDehydrated;
              if ((j && (Ml(h, d).flags |= 256), (d = Tu(h, d, !1)), d !== 2)) {
                if (gu && !j) {
                  (h.errorRecoveryDisabledLanes |= i), (Ja |= i), (n = 4);
                  break e;
                }
                (i = rt),
                  (rt = n),
                  i !== null && (rt === null ? (rt = i) : rt.push.apply(rt, i));
              }
              n = d;
            }
            if (((i = !1), n !== 2)) continue;
          }
        }
        if (n === 1) {
          Ml(e, 0), wa(e, t, 0, !0);
          break;
        }
        e: {
          switch (((l = e), (i = n), i)) {
            case 0:
            case 1:
              throw Error(c(345));
            case 4:
              if ((t & 4194048) !== t) break;
            case 6:
              wa(l, t, _t, !va);
              break e;
            case 2:
              rt = null;
              break;
            case 3:
            case 5:
              break;
            default:
              throw Error(c(329));
          }
          if ((t & 62914560) === t && ((n = vu + 300 - Dt()), 10 < n)) {
            if ((wa(l, t, _t, !va), ns(l, 0, !0) !== 0)) break e;
            l.timeoutHandle = Pd(
              wd.bind(null, l, a, rt, Ks, bu, t, _t, Ja, Rl, va, i, 2, -0, 0),
              n
            );
            break e;
          }
          wd(l, a, rt, Ks, bu, t, _t, Ja, Rl, va, i, 0, -0, 0);
        }
      }
      break;
    } while (!0);
    Bt(e);
  }
  function wd(e, t, a, l, n, i, d, h, j, O, V, X, z, k) {
    if (
      ((e.timeoutHandle = -1),
      (X = t.subtreeFlags),
      (X & 8192 || (X & 16785408) === 16785408) &&
        ((Un = { stylesheets: null, count: 0, unsuspend: Pp }),
        gd(t),
        (X = ex()),
        X !== null))
    ) {
      (e.cancelPendingCommit = X(
        Od.bind(null, e, t, i, a, l, n, d, h, j, V, 1, z, k)
      )),
        wa(e, i, d, !O);
      return;
    }
    Od(e, t, i, a, l, n, d, h, j);
  }
  function gp(e) {
    for (var t = e; ; ) {
      var a = t.tag;
      if (
        (a === 0 || a === 11 || a === 15) &&
        t.flags & 16384 &&
        ((a = t.updateQueue), a !== null && ((a = a.stores), a !== null))
      )
        for (var l = 0; l < a.length; l++) {
          var n = a[l],
            i = n.getSnapshot;
          n = n.value;
          try {
            if (!dt(i(), n)) return !1;
          } catch {
            return !1;
          }
        }
      if (((a = t.child), t.subtreeFlags & 16384 && a !== null))
        (a.return = t), (t = a);
      else {
        if (t === e) break;
        for (; t.sibling === null; ) {
          if (t.return === null || t.return === e) return !0;
          t = t.return;
        }
        (t.sibling.return = t.return), (t = t.sibling);
      }
    }
    return !0;
  }
  function wa(e, t, a, l) {
    (t &= ~yu),
      (t &= ~Ja),
      (e.suspendedLanes |= t),
      (e.pingedLanes &= ~t),
      l && (e.warmLanes |= t),
      (l = e.expirationTimes);
    for (var n = t; 0 < n; ) {
      var i = 31 - ft(n),
        d = 1 << i;
      (l[i] = -1), (n &= ~d);
    }
    a !== 0 && Dc(e, a, t);
  }
  function Js() {
    return (Ne & 6) === 0 ? (Rn(0), !1) : !0;
  }
  function wu() {
    if (xe !== null) {
      if (we === 0) var e = xe.return;
      else (e = xe), (Kt = Va = null), Yr(e), (wl = null), (bn = 0), (e = xe);
      for (; e !== null; ) ld(e.alternate, e), (e = e.return);
      xe = null;
    }
  }
  function Ml(e, t) {
    var a = e.timeoutHandle;
    a !== -1 && ((e.timeoutHandle = -1), Up(a)),
      (a = e.cancelPendingCommit),
      a !== null && ((e.cancelPendingCommit = null), a()),
      wu(),
      (Ce = e),
      (xe = a = Xt(e.current, null)),
      (ye = t),
      (we = 0),
      (xt = null),
      (va = !1),
      (Al = Xl(e, t)),
      (gu = !1),
      (Rl = _t = yu = Ja = ja = Le = 0),
      (rt = _n = null),
      (bu = !1),
      (t & 8) !== 0 && (t |= t & 32);
    var l = e.entangledLanes;
    if (l !== 0)
      for (e = e.entanglements, l &= t; 0 < l; ) {
        var n = 31 - ft(l),
          i = 1 << n;
        (t |= e[n]), (l &= ~i);
      }
    return (ta = t), xs(), a;
  }
  function Ed(e, t) {
    (me = null),
      (S.H = Us),
      t === on || t === Es
        ? ((t = Yo()), (we = 3))
        : t === Bo
        ? ((t = Yo()), (we = 4))
        : (we =
            t === Gf
              ? 8
              : t !== null &&
                typeof t == "object" &&
                typeof t.then == "function"
              ? 6
              : 1),
      (xt = t),
      xe === null && ((Le = 1), qs(e, St(t, e.current)));
  }
  function Td() {
    var e = S.H;
    return (S.H = Us), e === null ? Us : e;
  }
  function _d() {
    var e = S.A;
    return (S.A = pp), e;
  }
  function Eu() {
    (Le = 4),
      va || ((ye & 4194048) !== ye && Tt.current !== null) || (Al = !0),
      ((ja & 134217727) === 0 && (Ja & 134217727) === 0) ||
        Ce === null ||
        wa(Ce, ye, _t, !1);
  }
  function Tu(e, t, a) {
    var l = Ne;
    Ne |= 2;
    var n = Td(),
      i = _d();
    (Ce !== e || ye !== t) && ((Ks = null), Ml(e, t)), (t = !1);
    var d = Le;
    e: do
      try {
        if (we !== 0 && xe !== null) {
          var h = xe,
            j = xt;
          switch (we) {
            case 8:
              wu(), (d = 6);
              break e;
            case 3:
            case 2:
            case 9:
            case 6:
              Tt.current === null && (t = !0);
              var O = we;
              if (((we = 0), (xt = null), Dl(e, h, j, O), a && Al)) {
                d = 0;
                break e;
              }
              break;
            default:
              (O = we), (we = 0), (xt = null), Dl(e, h, j, O);
          }
        }
        yp(), (d = Le);
        break;
      } catch (V) {
        Ed(e, V);
      }
    while (!0);
    return (
      t && e.shellSuspendCounter++,
      (Kt = Va = null),
      (Ne = l),
      (S.H = n),
      (S.A = i),
      xe === null && ((Ce = null), (ye = 0), xs()),
      d
    );
  }
  function yp() {
    for (; xe !== null; ) Ad(xe);
  }
  function bp(e, t) {
    var a = Ne;
    Ne |= 2;
    var l = Td(),
      n = _d();
    Ce !== e || ye !== t
      ? ((Ks = null), (Zs = Dt() + 500), Ml(e, t))
      : (Al = Xl(e, t));
    e: do
      try {
        if (we !== 0 && xe !== null) {
          t = xe;
          var i = xt;
          t: switch (we) {
            case 1:
              (we = 0), (xt = null), Dl(e, t, i, 1);
              break;
            case 2:
            case 9:
              if (Ho(i)) {
                (we = 0), (xt = null), Rd(t);
                break;
              }
              (t = function () {
                (we !== 2 && we !== 9) || Ce !== e || (we = 7), Bt(e);
              }),
                i.then(t, t);
              break e;
            case 3:
              we = 7;
              break e;
            case 4:
              we = 5;
              break e;
            case 7:
              Ho(i)
                ? ((we = 0), (xt = null), Rd(t))
                : ((we = 0), (xt = null), Dl(e, t, i, 7));
              break;
            case 5:
              var d = null;
              switch (xe.tag) {
                case 26:
                  d = xe.memoizedState;
                case 5:
                case 27:
                  var h = xe;
                  if (!d || om(d)) {
                    (we = 0), (xt = null);
                    var j = h.sibling;
                    if (j !== null) xe = j;
                    else {
                      var O = h.return;
                      O !== null ? ((xe = O), $s(O)) : (xe = null);
                    }
                    break t;
                  }
              }
              (we = 0), (xt = null), Dl(e, t, i, 5);
              break;
            case 6:
              (we = 0), (xt = null), Dl(e, t, i, 6);
              break;
            case 8:
              wu(), (Le = 6);
              break e;
            default:
              throw Error(c(462));
          }
        }
        vp();
        break;
      } catch (V) {
        Ed(e, V);
      }
    while (!0);
    return (
      (Kt = Va = null),
      (S.H = l),
      (S.A = n),
      (Ne = a),
      xe !== null ? 0 : ((Ce = null), (ye = 0), xs(), Le)
    );
  }
  function vp() {
    for (; xe !== null && !Vh(); ) Ad(xe);
  }
  function Ad(e) {
    var t = td(e.alternate, e, ta);
    (e.memoizedProps = e.pendingProps), t === null ? $s(e) : (xe = t);
  }
  function Rd(e) {
    var t = e,
      a = t.alternate;
    switch (t.tag) {
      case 15:
      case 0:
        t = $f(a, t, t.pendingProps, t.type, void 0, ye);
        break;
      case 11:
        t = $f(a, t, t.pendingProps, t.type.render, t.ref, ye);
        break;
      case 5:
        Yr(t);
      default:
        ld(a, t), (t = xe = Ro(t, ta)), (t = td(a, t, ta));
    }
    (e.memoizedProps = e.pendingProps), t === null ? $s(e) : (xe = t);
  }
  function Dl(e, t, a, l) {
    (Kt = Va = null), Yr(t), (wl = null), (bn = 0);
    var n = t.return;
    try {
      if (cp(e, n, t, a, ye)) {
        (Le = 1), qs(e, St(a, e.current)), (xe = null);
        return;
      }
    } catch (i) {
      if (n !== null) throw ((xe = n), i);
      (Le = 1), qs(e, St(a, e.current)), (xe = null);
      return;
    }
    t.flags & 32768
      ? (Se || l === 1
          ? (e = !0)
          : Al || (ye & 536870912) !== 0
          ? (e = !1)
          : ((va = e = !0),
            (l === 2 || l === 9 || l === 3 || l === 6) &&
              ((l = Tt.current),
              l !== null && l.tag === 13 && (l.flags |= 16384))),
        Cd(t, e))
      : $s(t);
  }
  function $s(e) {
    var t = e;
    do {
      if ((t.flags & 32768) !== 0) {
        Cd(t, va);
        return;
      }
      e = t.return;
      var a = fp(t.alternate, t, ta);
      if (a !== null) {
        xe = a;
        return;
      }
      if (((t = t.sibling), t !== null)) {
        xe = t;
        return;
      }
      xe = t = e;
    } while (t !== null);
    Le === 0 && (Le = 5);
  }
  function Cd(e, t) {
    do {
      var a = dp(e.alternate, e);
      if (a !== null) {
        (a.flags &= 32767), (xe = a);
        return;
      }
      if (
        ((a = e.return),
        a !== null &&
          ((a.flags |= 32768), (a.subtreeFlags = 0), (a.deletions = null)),
        !t && ((e = e.sibling), e !== null))
      ) {
        xe = e;
        return;
      }
      xe = e = a;
    } while (e !== null);
    (Le = 6), (xe = null);
  }
  function Od(e, t, a, l, n, i, d, h, j) {
    e.cancelPendingCommit = null;
    do Fs();
    while ($e !== 0);
    if ((Ne & 6) !== 0) throw Error(c(327));
    if (t !== null) {
      if (t === e.current) throw Error(c(177));
      if (
        ((i = t.lanes | t.childLanes),
        (i |= xr),
        Ph(e, a, i, d, h, j),
        e === Ce && ((xe = Ce = null), (ye = 0)),
        (Cl = t),
        (Na = e),
        (Ol = a),
        (ju = i),
        (Su = n),
        (jd = l),
        (t.subtreeFlags & 10256) !== 0 || (t.flags & 10256) !== 0
          ? ((e.callbackNode = null),
            (e.callbackPriority = 0),
            wp(ts, function () {
              return kd(), null;
            }))
          : ((e.callbackNode = null), (e.callbackPriority = 0)),
        (l = (t.flags & 13878) !== 0),
        (t.subtreeFlags & 13878) !== 0 || l)
      ) {
        (l = S.T), (S.T = null), (n = H.p), (H.p = 2), (d = Ne), (Ne |= 4);
        try {
          mp(e, t, a);
        } finally {
          (Ne = d), (H.p = n), (S.T = l);
        }
      }
      ($e = 1), Md(), Dd(), zd();
    }
  }
  function Md() {
    if ($e === 1) {
      $e = 0;
      var e = Na,
        t = Cl,
        a = (t.flags & 13878) !== 0;
      if ((t.subtreeFlags & 13878) !== 0 || a) {
        (a = S.T), (S.T = null);
        var l = H.p;
        H.p = 2;
        var n = Ne;
        Ne |= 4;
        try {
          hd(t, e);
          var i = Bu,
            d = bo(e.containerInfo),
            h = i.focusedElem,
            j = i.selectionRange;
          if (
            d !== h &&
            h &&
            h.ownerDocument &&
            yo(h.ownerDocument.documentElement, h)
          ) {
            if (j !== null && fr(h)) {
              var O = j.start,
                V = j.end;
              if ((V === void 0 && (V = O), "selectionStart" in h))
                (h.selectionStart = O),
                  (h.selectionEnd = Math.min(V, h.value.length));
              else {
                var X = h.ownerDocument || document,
                  z = (X && X.defaultView) || window;
                if (z.getSelection) {
                  var k = z.getSelection(),
                    ie = h.textContent.length,
                    ne = Math.min(j.start, ie),
                    _e = j.end === void 0 ? ne : Math.min(j.end, ie);
                  !k.extend && ne > _e && ((d = _e), (_e = ne), (ne = d));
                  var _ = go(h, ne),
                    E = go(h, _e);
                  if (
                    _ &&
                    E &&
                    (k.rangeCount !== 1 ||
                      k.anchorNode !== _.node ||
                      k.anchorOffset !== _.offset ||
                      k.focusNode !== E.node ||
                      k.focusOffset !== E.offset)
                  ) {
                    var A = X.createRange();
                    A.setStart(_.node, _.offset),
                      k.removeAllRanges(),
                      ne > _e
                        ? (k.addRange(A), k.extend(E.node, E.offset))
                        : (A.setEnd(E.node, E.offset), k.addRange(A));
                  }
                }
              }
            }
            for (X = [], k = h; (k = k.parentNode); )
              k.nodeType === 1 &&
                X.push({ element: k, left: k.scrollLeft, top: k.scrollTop });
            for (
              typeof h.focus == "function" && h.focus(), h = 0;
              h < X.length;
              h++
            ) {
              var G = X[h];
              (G.element.scrollLeft = G.left), (G.element.scrollTop = G.top);
            }
          }
          (ui = !!Lu), (Bu = Lu = null);
        } finally {
          (Ne = n), (H.p = l), (S.T = a);
        }
      }
      (e.current = t), ($e = 2);
    }
  }
  function Dd() {
    if ($e === 2) {
      $e = 0;
      var e = Na,
        t = Cl,
        a = (t.flags & 8772) !== 0;
      if ((t.subtreeFlags & 8772) !== 0 || a) {
        (a = S.T), (S.T = null);
        var l = H.p;
        H.p = 2;
        var n = Ne;
        Ne |= 4;
        try {
          od(e, t.alternate, t);
        } finally {
          (Ne = n), (H.p = l), (S.T = a);
        }
      }
      $e = 3;
    }
  }
  function zd() {
    if ($e === 4 || $e === 3) {
      ($e = 0), Gh();
      var e = Na,
        t = Cl,
        a = Ol,
        l = jd;
      (t.subtreeFlags & 10256) !== 0 || (t.flags & 10256) !== 0
        ? ($e = 5)
        : (($e = 0), (Cl = Na = null), Ud(e, e.pendingLanes));
      var n = e.pendingLanes;
      if (
        (n === 0 && (Sa = null),
        Xi(a),
        (t = t.stateNode),
        ot && typeof ot.onCommitFiberRoot == "function")
      )
        try {
          ot.onCommitFiberRoot(Gl, t, void 0, (t.current.flags & 128) === 128);
        } catch {}
      if (l !== null) {
        (t = S.T), (n = H.p), (H.p = 2), (S.T = null);
        try {
          for (var i = e.onRecoverableError, d = 0; d < l.length; d++) {
            var h = l[d];
            i(h.value, { componentStack: h.stack });
          }
        } finally {
          (S.T = t), (H.p = n);
        }
      }
      (Ol & 3) !== 0 && Fs(),
        Bt(e),
        (n = e.pendingLanes),
        (a & 4194090) !== 0 && (n & 42) !== 0
          ? e === Nu
            ? An++
            : ((An = 0), (Nu = e))
          : (An = 0),
        Rn(0);
    }
  }
  function Ud(e, t) {
    (e.pooledCacheLanes &= t) === 0 &&
      ((t = e.pooledCache), t != null && ((e.pooledCache = null), un(t)));
  }
  function Fs(e) {
    return Md(), Dd(), zd(), kd();
  }
  function kd() {
    if ($e !== 5) return !1;
    var e = Na,
      t = ju;
    ju = 0;
    var a = Xi(Ol),
      l = S.T,
      n = H.p;
    try {
      (H.p = 32 > a ? 32 : a), (S.T = null), (a = Su), (Su = null);
      var i = Na,
        d = Ol;
      if ((($e = 0), (Cl = Na = null), (Ol = 0), (Ne & 6) !== 0))
        throw Error(c(331));
      var h = Ne;
      if (
        ((Ne |= 4),
        bd(i.current),
        xd(i, i.current, d, a),
        (Ne = h),
        Rn(0, !1),
        ot && typeof ot.onPostCommitFiberRoot == "function")
      )
        try {
          ot.onPostCommitFiberRoot(Gl, i);
        } catch {}
      return !0;
    } finally {
      (H.p = n), (S.T = l), Ud(e, t);
    }
  }
  function Ld(e, t, a) {
    (t = St(a, t)),
      (t = tu(e.stateNode, t, 2)),
      (e = ma(e, t, 2)),
      e !== null && (Ql(e, 2), Bt(e));
  }
  function Ae(e, t, a) {
    if (e.tag === 3) Ld(e, e, a);
    else
      for (; t !== null; ) {
        if (t.tag === 3) {
          Ld(t, e, a);
          break;
        } else if (t.tag === 1) {
          var l = t.stateNode;
          if (
            typeof t.type.getDerivedStateFromError == "function" ||
            (typeof l.componentDidCatch == "function" &&
              (Sa === null || !Sa.has(l)))
          ) {
            (e = St(a, e)),
              (a = Yf(2)),
              (l = ma(t, a, 2)),
              l !== null && (Vf(a, l, t, e), Ql(l, 2), Bt(l));
            break;
          }
        }
        t = t.return;
      }
  }
  function _u(e, t, a) {
    var l = e.pingCache;
    if (l === null) {
      l = e.pingCache = new xp();
      var n = new Set();
      l.set(t, n);
    } else (n = l.get(t)), n === void 0 && ((n = new Set()), l.set(t, n));
    n.has(a) ||
      ((gu = !0), n.add(a), (e = jp.bind(null, e, t, a)), t.then(e, e));
  }
  function jp(e, t, a) {
    var l = e.pingCache;
    l !== null && l.delete(t),
      (e.pingedLanes |= e.suspendedLanes & a),
      (e.warmLanes &= ~a),
      Ce === e &&
        (ye & a) === a &&
        (Le === 4 || (Le === 3 && (ye & 62914560) === ye && 300 > Dt() - vu)
          ? (Ne & 2) === 0 && Ml(e, 0)
          : (yu |= a),
        Rl === ye && (Rl = 0)),
      Bt(e);
  }
  function Bd(e, t) {
    t === 0 && (t = Mc()), (e = hl(e, t)), e !== null && (Ql(e, t), Bt(e));
  }
  function Sp(e) {
    var t = e.memoizedState,
      a = 0;
    t !== null && (a = t.retryLane), Bd(e, a);
  }
  function Np(e, t) {
    var a = 0;
    switch (e.tag) {
      case 13:
        var l = e.stateNode,
          n = e.memoizedState;
        n !== null && (a = n.retryLane);
        break;
      case 19:
        l = e.stateNode;
        break;
      case 22:
        l = e.stateNode._retryCache;
        break;
      default:
        throw Error(c(314));
    }
    l !== null && l.delete(t), Bd(e, a);
  }
  function wp(e, t) {
    return qi(e, t);
  }
  var Ws = null,
    zl = null,
    Au = !1,
    Ps = !1,
    Ru = !1,
    $a = 0;
  function Bt(e) {
    e !== zl &&
      e.next === null &&
      (zl === null ? (Ws = zl = e) : (zl = zl.next = e)),
      (Ps = !0),
      Au || ((Au = !0), Tp());
  }
  function Rn(e, t) {
    if (!Ru && Ps) {
      Ru = !0;
      do
        for (var a = !1, l = Ws; l !== null; ) {
          if (e !== 0) {
            var n = l.pendingLanes;
            if (n === 0) var i = 0;
            else {
              var d = l.suspendedLanes,
                h = l.pingedLanes;
              (i = (1 << (31 - ft(42 | e) + 1)) - 1),
                (i &= n & ~(d & ~h)),
                (i = i & 201326741 ? (i & 201326741) | 1 : i ? i | 2 : 0);
            }
            i !== 0 && ((a = !0), Vd(l, i));
          } else
            (i = ye),
              (i = ns(
                l,
                l === Ce ? i : 0,
                l.cancelPendingCommit !== null || l.timeoutHandle !== -1
              )),
              (i & 3) === 0 || Xl(l, i) || ((a = !0), Vd(l, i));
          l = l.next;
        }
      while (a);
      Ru = !1;
    }
  }
  function Ep() {
    Hd();
  }
  function Hd() {
    Ps = Au = !1;
    var e = 0;
    $a !== 0 && (zp() && (e = $a), ($a = 0));
    for (var t = Dt(), a = null, l = Ws; l !== null; ) {
      var n = l.next,
        i = qd(l, t);
      i === 0
        ? ((l.next = null),
          a === null ? (Ws = n) : (a.next = n),
          n === null && (zl = a))
        : ((a = l), (e !== 0 || (i & 3) !== 0) && (Ps = !0)),
        (l = n);
    }
    Rn(e);
  }
  function qd(e, t) {
    for (
      var a = e.suspendedLanes,
        l = e.pingedLanes,
        n = e.expirationTimes,
        i = e.pendingLanes & -62914561;
      0 < i;

    ) {
      var d = 31 - ft(i),
        h = 1 << d,
        j = n[d];
      j === -1
        ? ((h & a) === 0 || (h & l) !== 0) && (n[d] = Wh(h, t))
        : j <= t && (e.expiredLanes |= h),
        (i &= ~h);
    }
    if (
      ((t = Ce),
      (a = ye),
      (a = ns(
        e,
        e === t ? a : 0,
        e.cancelPendingCommit !== null || e.timeoutHandle !== -1
      )),
      (l = e.callbackNode),
      a === 0 ||
        (e === t && (we === 2 || we === 9)) ||
        e.cancelPendingCommit !== null)
    )
      return (
        l !== null && l !== null && Yi(l),
        (e.callbackNode = null),
        (e.callbackPriority = 0)
      );
    if ((a & 3) === 0 || Xl(e, a)) {
      if (((t = a & -a), t === e.callbackPriority)) return t;
      switch ((l !== null && Yi(l), Xi(a))) {
        case 2:
        case 8:
          a = Rc;
          break;
        case 32:
          a = ts;
          break;
        case 268435456:
          a = Cc;
          break;
        default:
          a = ts;
      }
      return (
        (l = Yd.bind(null, e)),
        (a = qi(a, l)),
        (e.callbackPriority = t),
        (e.callbackNode = a),
        t
      );
    }
    return (
      l !== null && l !== null && Yi(l),
      (e.callbackPriority = 2),
      (e.callbackNode = null),
      2
    );
  }
  function Yd(e, t) {
    if ($e !== 0 && $e !== 5)
      return (e.callbackNode = null), (e.callbackPriority = 0), null;
    var a = e.callbackNode;
    if (Fs() && e.callbackNode !== a) return null;
    var l = ye;
    return (
      (l = ns(
        e,
        e === Ce ? l : 0,
        e.cancelPendingCommit !== null || e.timeoutHandle !== -1
      )),
      l === 0
        ? null
        : (Nd(e, l, t),
          qd(e, Dt()),
          e.callbackNode != null && e.callbackNode === a
            ? Yd.bind(null, e)
            : null)
    );
  }
  function Vd(e, t) {
    if (Fs()) return null;
    Nd(e, t, !0);
  }
  function Tp() {
    kp(function () {
      (Ne & 6) !== 0 ? qi(Ac, Ep) : Hd();
    });
  }
  function Cu() {
    return $a === 0 && ($a = Oc()), $a;
  }
  function Gd(e) {
    return e == null || typeof e == "symbol" || typeof e == "boolean"
      ? null
      : typeof e == "function"
      ? e
      : cs("" + e);
  }
  function Xd(e, t) {
    var a = t.ownerDocument.createElement("input");
    return (
      (a.name = t.name),
      (a.value = t.value),
      e.id && a.setAttribute("form", e.id),
      t.parentNode.insertBefore(a, t),
      (e = new FormData(e)),
      a.parentNode.removeChild(a),
      e
    );
  }
  function _p(e, t, a, l, n) {
    if (t === "submit" && a && a.stateNode === n) {
      var i = Gd((n[lt] || null).action),
        d = l.submitter;
      d &&
        ((t = (t = d[lt] || null)
          ? Gd(t.formAction)
          : d.getAttribute("formAction")),
        t !== null && ((i = t), (d = null)));
      var h = new ms("action", "action", null, l, n);
      e.push({
        event: h,
        listeners: [
          {
            instance: null,
            listener: function () {
              if (l.defaultPrevented) {
                if ($a !== 0) {
                  var j = d ? Xd(n, d) : new FormData(n);
                  Fr(
                    a,
                    { pending: !0, data: j, method: n.method, action: i },
                    null,
                    j
                  );
                }
              } else
                typeof i == "function" &&
                  (h.preventDefault(),
                  (j = d ? Xd(n, d) : new FormData(n)),
                  Fr(
                    a,
                    { pending: !0, data: j, method: n.method, action: i },
                    i,
                    j
                  ));
            },
            currentTarget: n,
          },
        ],
      });
    }
  }
  for (var Ou = 0; Ou < pr.length; Ou++) {
    var Mu = pr[Ou],
      Ap = Mu.toLowerCase(),
      Rp = Mu[0].toUpperCase() + Mu.slice(1);
    Rt(Ap, "on" + Rp);
  }
  Rt(So, "onAnimationEnd"),
    Rt(No, "onAnimationIteration"),
    Rt(wo, "onAnimationStart"),
    Rt("dblclick", "onDoubleClick"),
    Rt("focusin", "onFocus"),
    Rt("focusout", "onBlur"),
    Rt(Z0, "onTransitionRun"),
    Rt(K0, "onTransitionStart"),
    Rt(J0, "onTransitionCancel"),
    Rt(Eo, "onTransitionEnd"),
    nl("onMouseEnter", ["mouseout", "mouseover"]),
    nl("onMouseLeave", ["mouseout", "mouseover"]),
    nl("onPointerEnter", ["pointerout", "pointerover"]),
    nl("onPointerLeave", ["pointerout", "pointerover"]),
    Da(
      "onChange",
      "change click focusin focusout input keydown keyup selectionchange".split(
        " "
      )
    ),
    Da(
      "onSelect",
      "focusout contextmenu dragend focusin keydown keyup mousedown mouseup selectionchange".split(
        " "
      )
    ),
    Da("onBeforeInput", ["compositionend", "keypress", "textInput", "paste"]),
    Da(
      "onCompositionEnd",
      "compositionend focusout keydown keypress keyup mousedown".split(" ")
    ),
    Da(
      "onCompositionStart",
      "compositionstart focusout keydown keypress keyup mousedown".split(" ")
    ),
    Da(
      "onCompositionUpdate",
      "compositionupdate focusout keydown keypress keyup mousedown".split(" ")
    );
  var Cn =
      "abort canplay canplaythrough durationchange emptied encrypted ended error loadeddata loadedmetadata loadstart pause play playing progress ratechange resize seeked seeking stalled suspend timeupdate volumechange waiting".split(
        " "
      ),
    Cp = new Set(
      "beforetoggle cancel close invalid load scroll scrollend toggle"
        .split(" ")
        .concat(Cn)
    );
  function Qd(e, t) {
    t = (t & 4) !== 0;
    for (var a = 0; a < e.length; a++) {
      var l = e[a],
        n = l.event;
      l = l.listeners;
      e: {
        var i = void 0;
        if (t)
          for (var d = l.length - 1; 0 <= d; d--) {
            var h = l[d],
              j = h.instance,
              O = h.currentTarget;
            if (((h = h.listener), j !== i && n.isPropagationStopped()))
              break e;
            (i = h), (n.currentTarget = O);
            try {
              i(n);
            } catch (V) {
              Hs(V);
            }
            (n.currentTarget = null), (i = j);
          }
        else
          for (d = 0; d < l.length; d++) {
            if (
              ((h = l[d]),
              (j = h.instance),
              (O = h.currentTarget),
              (h = h.listener),
              j !== i && n.isPropagationStopped())
            )
              break e;
            (i = h), (n.currentTarget = O);
            try {
              i(n);
            } catch (V) {
              Hs(V);
            }
            (n.currentTarget = null), (i = j);
          }
      }
    }
  }
  function ge(e, t) {
    var a = t[Qi];
    a === void 0 && (a = t[Qi] = new Set());
    var l = e + "__bubble";
    a.has(l) || (Zd(t, e, 2, !1), a.add(l));
  }
  function Du(e, t, a) {
    var l = 0;
    t && (l |= 4), Zd(a, e, l, t);
  }
  var Is = "_reactListening" + Math.random().toString(36).slice(2);
  function zu(e) {
    if (!e[Is]) {
      (e[Is] = !0),
        Lc.forEach(function (a) {
          a !== "selectionchange" && (Cp.has(a) || Du(a, !1, e), Du(a, !0, e));
        });
      var t = e.nodeType === 9 ? e : e.ownerDocument;
      t === null || t[Is] || ((t[Is] = !0), Du("selectionchange", !1, t));
    }
  }
  function Zd(e, t, a, l) {
    switch (xm(t)) {
      case 2:
        var n = lx;
        break;
      case 8:
        n = nx;
        break;
      default:
        n = Ju;
    }
    (a = n.bind(null, t, a, e)),
      (n = void 0),
      !ar ||
        (t !== "touchstart" && t !== "touchmove" && t !== "wheel") ||
        (n = !0),
      l
        ? n !== void 0
          ? e.addEventListener(t, a, { capture: !0, passive: n })
          : e.addEventListener(t, a, !0)
        : n !== void 0
        ? e.addEventListener(t, a, { passive: n })
        : e.addEventListener(t, a, !1);
  }
  function Uu(e, t, a, l, n) {
    var i = l;
    if ((t & 1) === 0 && (t & 2) === 0 && l !== null)
      e: for (;;) {
        if (l === null) return;
        var d = l.tag;
        if (d === 3 || d === 4) {
          var h = l.stateNode.containerInfo;
          if (h === n) break;
          if (d === 4)
            for (d = l.return; d !== null; ) {
              var j = d.tag;
              if ((j === 3 || j === 4) && d.stateNode.containerInfo === n)
                return;
              d = d.return;
            }
          for (; h !== null; ) {
            if (((d = tl(h)), d === null)) return;
            if (((j = d.tag), j === 5 || j === 6 || j === 26 || j === 27)) {
              l = i = d;
              continue e;
            }
            h = h.parentNode;
          }
        }
        l = l.return;
      }
    Wc(function () {
      var O = i,
        V = er(a),
        X = [];
      e: {
        var z = To.get(e);
        if (z !== void 0) {
          var k = ms,
            ie = e;
          switch (e) {
            case "keypress":
              if (fs(a) === 0) break e;
            case "keydown":
            case "keyup":
              k = w0;
              break;
            case "focusin":
              (ie = "focus"), (k = ir);
              break;
            case "focusout":
              (ie = "blur"), (k = ir);
              break;
            case "beforeblur":
            case "afterblur":
              k = ir;
              break;
            case "click":
              if (a.button === 2) break e;
            case "auxclick":
            case "dblclick":
            case "mousedown":
            case "mousemove":
            case "mouseup":
            case "mouseout":
            case "mouseover":
            case "contextmenu":
              k = eo;
              break;
            case "drag":
            case "dragend":
            case "dragenter":
            case "dragexit":
            case "dragleave":
            case "dragover":
            case "dragstart":
            case "drop":
              k = d0;
              break;
            case "touchcancel":
            case "touchend":
            case "touchmove":
            case "touchstart":
              k = _0;
              break;
            case So:
            case No:
            case wo:
              k = p0;
              break;
            case Eo:
              k = R0;
              break;
            case "scroll":
            case "scrollend":
              k = o0;
              break;
            case "wheel":
              k = O0;
              break;
            case "copy":
            case "cut":
            case "paste":
              k = g0;
              break;
            case "gotpointercapture":
            case "lostpointercapture":
            case "pointercancel":
            case "pointerdown":
            case "pointermove":
            case "pointerout":
            case "pointerover":
            case "pointerup":
              k = ao;
              break;
            case "toggle":
            case "beforetoggle":
              k = D0;
          }
          var ne = (t & 4) !== 0,
            _e = !ne && (e === "scroll" || e === "scrollend"),
            _ = ne ? (z !== null ? z + "Capture" : null) : z;
          ne = [];
          for (var E = O, A; E !== null; ) {
            var G = E;
            if (
              ((A = G.stateNode),
              (G = G.tag),
              (G !== 5 && G !== 26 && G !== 27) ||
                A === null ||
                _ === null ||
                ((G = Jl(E, _)), G != null && ne.push(On(E, G, A))),
              _e)
            )
              break;
            E = E.return;
          }
          0 < ne.length &&
            ((z = new k(z, ie, null, a, V)),
            X.push({ event: z, listeners: ne }));
        }
      }
      if ((t & 7) === 0) {
        e: {
          if (
            ((z = e === "mouseover" || e === "pointerover"),
            (k = e === "mouseout" || e === "pointerout"),
            z &&
              a !== Ii &&
              (ie = a.relatedTarget || a.fromElement) &&
              (tl(ie) || ie[el]))
          )
            break e;
          if (
            (k || z) &&
            ((z =
              V.window === V
                ? V
                : (z = V.ownerDocument)
                ? z.defaultView || z.parentWindow
                : window),
            k
              ? ((ie = a.relatedTarget || a.toElement),
                (k = O),
                (ie = ie ? tl(ie) : null),
                ie !== null &&
                  ((_e = m(ie)),
                  (ne = ie.tag),
                  ie !== _e || (ne !== 5 && ne !== 27 && ne !== 6)) &&
                  (ie = null))
              : ((k = null), (ie = O)),
            k !== ie)
          ) {
            if (
              ((ne = eo),
              (G = "onMouseLeave"),
              (_ = "onMouseEnter"),
              (E = "mouse"),
              (e === "pointerout" || e === "pointerover") &&
                ((ne = ao),
                (G = "onPointerLeave"),
                (_ = "onPointerEnter"),
                (E = "pointer")),
              (_e = k == null ? z : Kl(k)),
              (A = ie == null ? z : Kl(ie)),
              (z = new ne(G, E + "leave", k, a, V)),
              (z.target = _e),
              (z.relatedTarget = A),
              (G = null),
              tl(V) === O &&
                ((ne = new ne(_, E + "enter", ie, a, V)),
                (ne.target = A),
                (ne.relatedTarget = _e),
                (G = ne)),
              (_e = G),
              k && ie)
            )
              t: {
                for (ne = k, _ = ie, E = 0, A = ne; A; A = Ul(A)) E++;
                for (A = 0, G = _; G; G = Ul(G)) A++;
                for (; 0 < E - A; ) (ne = Ul(ne)), E--;
                for (; 0 < A - E; ) (_ = Ul(_)), A--;
                for (; E--; ) {
                  if (ne === _ || (_ !== null && ne === _.alternate)) break t;
                  (ne = Ul(ne)), (_ = Ul(_));
                }
                ne = null;
              }
            else ne = null;
            k !== null && Kd(X, z, k, ne, !1),
              ie !== null && _e !== null && Kd(X, _e, ie, ne, !0);
          }
        }
        e: {
          if (
            ((z = O ? Kl(O) : window),
            (k = z.nodeName && z.nodeName.toLowerCase()),
            k === "select" || (k === "input" && z.type === "file"))
          )
            var I = oo;
          else if (uo(z))
            if (fo) I = G0;
            else {
              I = Y0;
              var he = q0;
            }
          else
            (k = z.nodeName),
              !k ||
              k.toLowerCase() !== "input" ||
              (z.type !== "checkbox" && z.type !== "radio")
                ? O && Pi(O.elementType) && (I = oo)
                : (I = V0);
          if (I && (I = I(e, O))) {
            co(X, I, a, V);
            break e;
          }
          he && he(e, z, O),
            e === "focusout" &&
              O &&
              z.type === "number" &&
              O.memoizedProps.value != null &&
              Wi(z, "number", z.value);
        }
        switch (((he = O ? Kl(O) : window), e)) {
          case "focusin":
            (uo(he) || he.contentEditable === "true") &&
              ((fl = he), (dr = O), (an = null));
            break;
          case "focusout":
            an = dr = fl = null;
            break;
          case "mousedown":
            mr = !0;
            break;
          case "contextmenu":
          case "mouseup":
          case "dragend":
            (mr = !1), vo(X, a, V);
            break;
          case "selectionchange":
            if (Q0) break;
          case "keydown":
          case "keyup":
            vo(X, a, V);
        }
        var ae;
        if (ur)
          e: {
            switch (e) {
              case "compositionstart":
                var se = "onCompositionStart";
                break e;
              case "compositionend":
                se = "onCompositionEnd";
                break e;
              case "compositionupdate":
                se = "onCompositionUpdate";
                break e;
            }
            se = void 0;
          }
        else
          ol
            ? io(e, a) && (se = "onCompositionEnd")
            : e === "keydown" &&
              a.keyCode === 229 &&
              (se = "onCompositionStart");
        se &&
          (lo &&
            a.locale !== "ko" &&
            (ol || se !== "onCompositionStart"
              ? se === "onCompositionEnd" && ol && (ae = Pc())
              : ((ca = V),
                (lr = "value" in ca ? ca.value : ca.textContent),
                (ol = !0))),
          (he = ei(O, se)),
          0 < he.length &&
            ((se = new to(se, e, null, a, V)),
            X.push({ event: se, listeners: he }),
            ae
              ? (se.data = ae)
              : ((ae = ro(a)), ae !== null && (se.data = ae)))),
          (ae = U0 ? k0(e, a) : L0(e, a)) &&
            ((se = ei(O, "onBeforeInput")),
            0 < se.length &&
              ((he = new to("onBeforeInput", "beforeinput", null, a, V)),
              X.push({ event: he, listeners: se }),
              (he.data = ae))),
          _p(X, e, O, a, V);
      }
      Qd(X, t);
    });
  }
  function On(e, t, a) {
    return { instance: e, listener: t, currentTarget: a };
  }
  function ei(e, t) {
    for (var a = t + "Capture", l = []; e !== null; ) {
      var n = e,
        i = n.stateNode;
      if (
        ((n = n.tag),
        (n !== 5 && n !== 26 && n !== 27) ||
          i === null ||
          ((n = Jl(e, a)),
          n != null && l.unshift(On(e, n, i)),
          (n = Jl(e, t)),
          n != null && l.push(On(e, n, i))),
        e.tag === 3)
      )
        return l;
      e = e.return;
    }
    return [];
  }
  function Ul(e) {
    if (e === null) return null;
    do e = e.return;
    while (e && e.tag !== 5 && e.tag !== 27);
    return e || null;
  }
  function Kd(e, t, a, l, n) {
    for (var i = t._reactName, d = []; a !== null && a !== l; ) {
      var h = a,
        j = h.alternate,
        O = h.stateNode;
      if (((h = h.tag), j !== null && j === l)) break;
      (h !== 5 && h !== 26 && h !== 27) ||
        O === null ||
        ((j = O),
        n
          ? ((O = Jl(a, i)), O != null && d.unshift(On(a, O, j)))
          : n || ((O = Jl(a, i)), O != null && d.push(On(a, O, j)))),
        (a = a.return);
    }
    d.length !== 0 && e.push({ event: t, listeners: d });
  }
  var Op = /\r\n?/g,
    Mp = /\u0000|\uFFFD/g;
  function Jd(e) {
    return (typeof e == "string" ? e : "" + e)
      .replace(
        Op,
        `
`
      )
      .replace(Mp, "");
  }
  function $d(e, t) {
    return (t = Jd(t)), Jd(e) === t;
  }
  function ti() {}
  function Te(e, t, a, l, n, i) {
    switch (a) {
      case "children":
        typeof l == "string"
          ? t === "body" || (t === "textarea" && l === "") || rl(e, l)
          : (typeof l == "number" || typeof l == "bigint") &&
            t !== "body" &&
            rl(e, "" + l);
        break;
      case "className":
        is(e, "class", l);
        break;
      case "tabIndex":
        is(e, "tabindex", l);
        break;
      case "dir":
      case "role":
      case "viewBox":
      case "width":
      case "height":
        is(e, a, l);
        break;
      case "style":
        $c(e, l, i);
        break;
      case "data":
        if (t !== "object") {
          is(e, "data", l);
          break;
        }
      case "src":
      case "href":
        if (l === "" && (t !== "a" || a !== "href")) {
          e.removeAttribute(a);
          break;
        }
        if (
          l == null ||
          typeof l == "function" ||
          typeof l == "symbol" ||
          typeof l == "boolean"
        ) {
          e.removeAttribute(a);
          break;
        }
        (l = cs("" + l)), e.setAttribute(a, l);
        break;
      case "action":
      case "formAction":
        if (typeof l == "function") {
          e.setAttribute(
            a,
            "javascript:throw new Error('A React form was unexpectedly submitted. If you called form.submit() manually, consider using form.requestSubmit() instead. If you\\'re trying to use event.stopPropagation() in a submit event handler, consider also calling event.preventDefault().')"
          );
          break;
        } else
          typeof i == "function" &&
            (a === "formAction"
              ? (t !== "input" && Te(e, t, "name", n.name, n, null),
                Te(e, t, "formEncType", n.formEncType, n, null),
                Te(e, t, "formMethod", n.formMethod, n, null),
                Te(e, t, "formTarget", n.formTarget, n, null))
              : (Te(e, t, "encType", n.encType, n, null),
                Te(e, t, "method", n.method, n, null),
                Te(e, t, "target", n.target, n, null)));
        if (l == null || typeof l == "symbol" || typeof l == "boolean") {
          e.removeAttribute(a);
          break;
        }
        (l = cs("" + l)), e.setAttribute(a, l);
        break;
      case "onClick":
        l != null && (e.onclick = ti);
        break;
      case "onScroll":
        l != null && ge("scroll", e);
        break;
      case "onScrollEnd":
        l != null && ge("scrollend", e);
        break;
      case "dangerouslySetInnerHTML":
        if (l != null) {
          if (typeof l != "object" || !("__html" in l)) throw Error(c(61));
          if (((a = l.__html), a != null)) {
            if (n.children != null) throw Error(c(60));
            e.innerHTML = a;
          }
        }
        break;
      case "multiple":
        e.multiple = l && typeof l != "function" && typeof l != "symbol";
        break;
      case "muted":
        e.muted = l && typeof l != "function" && typeof l != "symbol";
        break;
      case "suppressContentEditableWarning":
      case "suppressHydrationWarning":
      case "defaultValue":
      case "defaultChecked":
      case "innerHTML":
      case "ref":
        break;
      case "autoFocus":
        break;
      case "xlinkHref":
        if (
          l == null ||
          typeof l == "function" ||
          typeof l == "boolean" ||
          typeof l == "symbol"
        ) {
          e.removeAttribute("xlink:href");
          break;
        }
        (a = cs("" + l)),
          e.setAttributeNS("http://www.w3.org/1999/xlink", "xlink:href", a);
        break;
      case "contentEditable":
      case "spellCheck":
      case "draggable":
      case "value":
      case "autoReverse":
      case "externalResourcesRequired":
      case "focusable":
      case "preserveAlpha":
        l != null && typeof l != "function" && typeof l != "symbol"
          ? e.setAttribute(a, "" + l)
          : e.removeAttribute(a);
        break;
      case "inert":
      case "allowFullScreen":
      case "async":
      case "autoPlay":
      case "controls":
      case "default":
      case "defer":
      case "disabled":
      case "disablePictureInPicture":
      case "disableRemotePlayback":
      case "formNoValidate":
      case "hidden":
      case "loop":
      case "noModule":
      case "noValidate":
      case "open":
      case "playsInline":
      case "readOnly":
      case "required":
      case "reversed":
      case "scoped":
      case "seamless":
      case "itemScope":
        l && typeof l != "function" && typeof l != "symbol"
          ? e.setAttribute(a, "")
          : e.removeAttribute(a);
        break;
      case "capture":
      case "download":
        l === !0
          ? e.setAttribute(a, "")
          : l !== !1 &&
            l != null &&
            typeof l != "function" &&
            typeof l != "symbol"
          ? e.setAttribute(a, l)
          : e.removeAttribute(a);
        break;
      case "cols":
      case "rows":
      case "size":
      case "span":
        l != null &&
        typeof l != "function" &&
        typeof l != "symbol" &&
        !isNaN(l) &&
        1 <= l
          ? e.setAttribute(a, l)
          : e.removeAttribute(a);
        break;
      case "rowSpan":
      case "start":
        l == null || typeof l == "function" || typeof l == "symbol" || isNaN(l)
          ? e.removeAttribute(a)
          : e.setAttribute(a, l);
        break;
      case "popover":
        ge("beforetoggle", e), ge("toggle", e), ss(e, "popover", l);
        break;
      case "xlinkActuate":
        Vt(e, "http://www.w3.org/1999/xlink", "xlink:actuate", l);
        break;
      case "xlinkArcrole":
        Vt(e, "http://www.w3.org/1999/xlink", "xlink:arcrole", l);
        break;
      case "xlinkRole":
        Vt(e, "http://www.w3.org/1999/xlink", "xlink:role", l);
        break;
      case "xlinkShow":
        Vt(e, "http://www.w3.org/1999/xlink", "xlink:show", l);
        break;
      case "xlinkTitle":
        Vt(e, "http://www.w3.org/1999/xlink", "xlink:title", l);
        break;
      case "xlinkType":
        Vt(e, "http://www.w3.org/1999/xlink", "xlink:type", l);
        break;
      case "xmlBase":
        Vt(e, "http://www.w3.org/XML/1998/namespace", "xml:base", l);
        break;
      case "xmlLang":
        Vt(e, "http://www.w3.org/XML/1998/namespace", "xml:lang", l);
        break;
      case "xmlSpace":
        Vt(e, "http://www.w3.org/XML/1998/namespace", "xml:space", l);
        break;
      case "is":
        ss(e, "is", l);
        break;
      case "innerText":
      case "textContent":
        break;
      default:
        (!(2 < a.length) ||
          (a[0] !== "o" && a[0] !== "O") ||
          (a[1] !== "n" && a[1] !== "N")) &&
          ((a = u0.get(a) || a), ss(e, a, l));
    }
  }
  function ku(e, t, a, l, n, i) {
    switch (a) {
      case "style":
        $c(e, l, i);
        break;
      case "dangerouslySetInnerHTML":
        if (l != null) {
          if (typeof l != "object" || !("__html" in l)) throw Error(c(61));
          if (((a = l.__html), a != null)) {
            if (n.children != null) throw Error(c(60));
            e.innerHTML = a;
          }
        }
        break;
      case "children":
        typeof l == "string"
          ? rl(e, l)
          : (typeof l == "number" || typeof l == "bigint") && rl(e, "" + l);
        break;
      case "onScroll":
        l != null && ge("scroll", e);
        break;
      case "onScrollEnd":
        l != null && ge("scrollend", e);
        break;
      case "onClick":
        l != null && (e.onclick = ti);
        break;
      case "suppressContentEditableWarning":
      case "suppressHydrationWarning":
      case "innerHTML":
      case "ref":
        break;
      case "innerText":
      case "textContent":
        break;
      default:
        if (!Bc.hasOwnProperty(a))
          e: {
            if (
              a[0] === "o" &&
              a[1] === "n" &&
              ((n = a.endsWith("Capture")),
              (t = a.slice(2, n ? a.length - 7 : void 0)),
              (i = e[lt] || null),
              (i = i != null ? i[a] : null),
              typeof i == "function" && e.removeEventListener(t, i, n),
              typeof l == "function")
            ) {
              typeof i != "function" &&
                i !== null &&
                (a in e
                  ? (e[a] = null)
                  : e.hasAttribute(a) && e.removeAttribute(a)),
                e.addEventListener(t, l, n);
              break e;
            }
            a in e
              ? (e[a] = l)
              : l === !0
              ? e.setAttribute(a, "")
              : ss(e, a, l);
          }
    }
  }
  function Fe(e, t, a) {
    switch (t) {
      case "div":
      case "span":
      case "svg":
      case "path":
      case "a":
      case "g":
      case "p":
      case "li":
        break;
      case "img":
        ge("error", e), ge("load", e);
        var l = !1,
          n = !1,
          i;
        for (i in a)
          if (a.hasOwnProperty(i)) {
            var d = a[i];
            if (d != null)
              switch (i) {
                case "src":
                  l = !0;
                  break;
                case "srcSet":
                  n = !0;
                  break;
                case "children":
                case "dangerouslySetInnerHTML":
                  throw Error(c(137, t));
                default:
                  Te(e, t, i, d, a, null);
              }
          }
        n && Te(e, t, "srcSet", a.srcSet, a, null),
          l && Te(e, t, "src", a.src, a, null);
        return;
      case "input":
        ge("invalid", e);
        var h = (i = d = n = null),
          j = null,
          O = null;
        for (l in a)
          if (a.hasOwnProperty(l)) {
            var V = a[l];
            if (V != null)
              switch (l) {
                case "name":
                  n = V;
                  break;
                case "type":
                  d = V;
                  break;
                case "checked":
                  j = V;
                  break;
                case "defaultChecked":
                  O = V;
                  break;
                case "value":
                  i = V;
                  break;
                case "defaultValue":
                  h = V;
                  break;
                case "children":
                case "dangerouslySetInnerHTML":
                  if (V != null) throw Error(c(137, t));
                  break;
                default:
                  Te(e, t, l, V, a, null);
              }
          }
        Qc(e, i, h, j, O, d, n, !1), rs(e);
        return;
      case "select":
        ge("invalid", e), (l = d = i = null);
        for (n in a)
          if (a.hasOwnProperty(n) && ((h = a[n]), h != null))
            switch (n) {
              case "value":
                i = h;
                break;
              case "defaultValue":
                d = h;
                break;
              case "multiple":
                l = h;
              default:
                Te(e, t, n, h, a, null);
            }
        (t = i),
          (a = d),
          (e.multiple = !!l),
          t != null ? il(e, !!l, t, !1) : a != null && il(e, !!l, a, !0);
        return;
      case "textarea":
        ge("invalid", e), (i = n = l = null);
        for (d in a)
          if (a.hasOwnProperty(d) && ((h = a[d]), h != null))
            switch (d) {
              case "value":
                l = h;
                break;
              case "defaultValue":
                n = h;
                break;
              case "children":
                i = h;
                break;
              case "dangerouslySetInnerHTML":
                if (h != null) throw Error(c(91));
                break;
              default:
                Te(e, t, d, h, a, null);
            }
        Kc(e, l, n, i), rs(e);
        return;
      case "option":
        for (j in a)
          if (a.hasOwnProperty(j) && ((l = a[j]), l != null))
            switch (j) {
              case "selected":
                e.selected =
                  l && typeof l != "function" && typeof l != "symbol";
                break;
              default:
                Te(e, t, j, l, a, null);
            }
        return;
      case "dialog":
        ge("beforetoggle", e), ge("toggle", e), ge("cancel", e), ge("close", e);
        break;
      case "iframe":
      case "object":
        ge("load", e);
        break;
      case "video":
      case "audio":
        for (l = 0; l < Cn.length; l++) ge(Cn[l], e);
        break;
      case "image":
        ge("error", e), ge("load", e);
        break;
      case "details":
        ge("toggle", e);
        break;
      case "embed":
      case "source":
      case "link":
        ge("error", e), ge("load", e);
      case "area":
      case "base":
      case "br":
      case "col":
      case "hr":
      case "keygen":
      case "meta":
      case "param":
      case "track":
      case "wbr":
      case "menuitem":
        for (O in a)
          if (a.hasOwnProperty(O) && ((l = a[O]), l != null))
            switch (O) {
              case "children":
              case "dangerouslySetInnerHTML":
                throw Error(c(137, t));
              default:
                Te(e, t, O, l, a, null);
            }
        return;
      default:
        if (Pi(t)) {
          for (V in a)
            a.hasOwnProperty(V) &&
              ((l = a[V]), l !== void 0 && ku(e, t, V, l, a, void 0));
          return;
        }
    }
    for (h in a)
      a.hasOwnProperty(h) && ((l = a[h]), l != null && Te(e, t, h, l, a, null));
  }
  function Dp(e, t, a, l) {
    switch (t) {
      case "div":
      case "span":
      case "svg":
      case "path":
      case "a":
      case "g":
      case "p":
      case "li":
        break;
      case "input":
        var n = null,
          i = null,
          d = null,
          h = null,
          j = null,
          O = null,
          V = null;
        for (k in a) {
          var X = a[k];
          if (a.hasOwnProperty(k) && X != null)
            switch (k) {
              case "checked":
                break;
              case "value":
                break;
              case "defaultValue":
                j = X;
              default:
                l.hasOwnProperty(k) || Te(e, t, k, null, l, X);
            }
        }
        for (var z in l) {
          var k = l[z];
          if (((X = a[z]), l.hasOwnProperty(z) && (k != null || X != null)))
            switch (z) {
              case "type":
                i = k;
                break;
              case "name":
                n = k;
                break;
              case "checked":
                O = k;
                break;
              case "defaultChecked":
                V = k;
                break;
              case "value":
                d = k;
                break;
              case "defaultValue":
                h = k;
                break;
              case "children":
              case "dangerouslySetInnerHTML":
                if (k != null) throw Error(c(137, t));
                break;
              default:
                k !== X && Te(e, t, z, k, l, X);
            }
        }
        Fi(e, d, h, j, O, V, i, n);
        return;
      case "select":
        k = d = h = z = null;
        for (i in a)
          if (((j = a[i]), a.hasOwnProperty(i) && j != null))
            switch (i) {
              case "value":
                break;
              case "multiple":
                k = j;
              default:
                l.hasOwnProperty(i) || Te(e, t, i, null, l, j);
            }
        for (n in l)
          if (
            ((i = l[n]),
            (j = a[n]),
            l.hasOwnProperty(n) && (i != null || j != null))
          )
            switch (n) {
              case "value":
                z = i;
                break;
              case "defaultValue":
                h = i;
                break;
              case "multiple":
                d = i;
              default:
                i !== j && Te(e, t, n, i, l, j);
            }
        (t = h),
          (a = d),
          (l = k),
          z != null
            ? il(e, !!a, z, !1)
            : !!l != !!a &&
              (t != null ? il(e, !!a, t, !0) : il(e, !!a, a ? [] : "", !1));
        return;
      case "textarea":
        k = z = null;
        for (h in a)
          if (
            ((n = a[h]),
            a.hasOwnProperty(h) && n != null && !l.hasOwnProperty(h))
          )
            switch (h) {
              case "value":
                break;
              case "children":
                break;
              default:
                Te(e, t, h, null, l, n);
            }
        for (d in l)
          if (
            ((n = l[d]),
            (i = a[d]),
            l.hasOwnProperty(d) && (n != null || i != null))
          )
            switch (d) {
              case "value":
                z = n;
                break;
              case "defaultValue":
                k = n;
                break;
              case "children":
                break;
              case "dangerouslySetInnerHTML":
                if (n != null) throw Error(c(91));
                break;
              default:
                n !== i && Te(e, t, d, n, l, i);
            }
        Zc(e, z, k);
        return;
      case "option":
        for (var ie in a)
          if (
            ((z = a[ie]),
            a.hasOwnProperty(ie) && z != null && !l.hasOwnProperty(ie))
          )
            switch (ie) {
              case "selected":
                e.selected = !1;
                break;
              default:
                Te(e, t, ie, null, l, z);
            }
        for (j in l)
          if (
            ((z = l[j]),
            (k = a[j]),
            l.hasOwnProperty(j) && z !== k && (z != null || k != null))
          )
            switch (j) {
              case "selected":
                e.selected =
                  z && typeof z != "function" && typeof z != "symbol";
                break;
              default:
                Te(e, t, j, z, l, k);
            }
        return;
      case "img":
      case "link":
      case "area":
      case "base":
      case "br":
      case "col":
      case "embed":
      case "hr":
      case "keygen":
      case "meta":
      case "param":
      case "source":
      case "track":
      case "wbr":
      case "menuitem":
        for (var ne in a)
          (z = a[ne]),
            a.hasOwnProperty(ne) &&
              z != null &&
              !l.hasOwnProperty(ne) &&
              Te(e, t, ne, null, l, z);
        for (O in l)
          if (
            ((z = l[O]),
            (k = a[O]),
            l.hasOwnProperty(O) && z !== k && (z != null || k != null))
          )
            switch (O) {
              case "children":
              case "dangerouslySetInnerHTML":
                if (z != null) throw Error(c(137, t));
                break;
              default:
                Te(e, t, O, z, l, k);
            }
        return;
      default:
        if (Pi(t)) {
          for (var _e in a)
            (z = a[_e]),
              a.hasOwnProperty(_e) &&
                z !== void 0 &&
                !l.hasOwnProperty(_e) &&
                ku(e, t, _e, void 0, l, z);
          for (V in l)
            (z = l[V]),
              (k = a[V]),
              !l.hasOwnProperty(V) ||
                z === k ||
                (z === void 0 && k === void 0) ||
                ku(e, t, V, z, l, k);
          return;
        }
    }
    for (var _ in a)
      (z = a[_]),
        a.hasOwnProperty(_) &&
          z != null &&
          !l.hasOwnProperty(_) &&
          Te(e, t, _, null, l, z);
    for (X in l)
      (z = l[X]),
        (k = a[X]),
        !l.hasOwnProperty(X) ||
          z === k ||
          (z == null && k == null) ||
          Te(e, t, X, z, l, k);
  }
  var Lu = null,
    Bu = null;
  function ai(e) {
    return e.nodeType === 9 ? e : e.ownerDocument;
  }
  function Fd(e) {
    switch (e) {
      case "http://www.w3.org/2000/svg":
        return 1;
      case "http://www.w3.org/1998/Math/MathML":
        return 2;
      default:
        return 0;
    }
  }
  function Wd(e, t) {
    if (e === 0)
      switch (t) {
        case "svg":
          return 1;
        case "math":
          return 2;
        default:
          return 0;
      }
    return e === 1 && t === "foreignObject" ? 0 : e;
  }
  function Hu(e, t) {
    return (
      e === "textarea" ||
      e === "noscript" ||
      typeof t.children == "string" ||
      typeof t.children == "number" ||
      typeof t.children == "bigint" ||
      (typeof t.dangerouslySetInnerHTML == "object" &&
        t.dangerouslySetInnerHTML !== null &&
        t.dangerouslySetInnerHTML.__html != null)
    );
  }
  var qu = null;
  function zp() {
    var e = window.event;
    return e && e.type === "popstate"
      ? e === qu
        ? !1
        : ((qu = e), !0)
      : ((qu = null), !1);
  }
  var Pd = typeof setTimeout == "function" ? setTimeout : void 0,
    Up = typeof clearTimeout == "function" ? clearTimeout : void 0,
    Id = typeof Promise == "function" ? Promise : void 0,
    kp =
      typeof queueMicrotask == "function"
        ? queueMicrotask
        : typeof Id < "u"
        ? function (e) {
            return Id.resolve(null).then(e).catch(Lp);
          }
        : Pd;
  function Lp(e) {
    setTimeout(function () {
      throw e;
    });
  }
  function Ea(e) {
    return e === "head";
  }
  function em(e, t) {
    var a = t,
      l = 0,
      n = 0;
    do {
      var i = a.nextSibling;
      if ((e.removeChild(a), i && i.nodeType === 8))
        if (((a = i.data), a === "/$")) {
          if (0 < l && 8 > l) {
            a = l;
            var d = e.ownerDocument;
            if ((a & 1 && Mn(d.documentElement), a & 2 && Mn(d.body), a & 4))
              for (a = d.head, Mn(a), d = a.firstChild; d; ) {
                var h = d.nextSibling,
                  j = d.nodeName;
                d[Zl] ||
                  j === "SCRIPT" ||
                  j === "STYLE" ||
                  (j === "LINK" && d.rel.toLowerCase() === "stylesheet") ||
                  a.removeChild(d),
                  (d = h);
              }
          }
          if (n === 0) {
            e.removeChild(i), qn(t);
            return;
          }
          n--;
        } else
          a === "$" || a === "$?" || a === "$!"
            ? n++
            : (l = a.charCodeAt(0) - 48);
      else l = 0;
      a = i;
    } while (a);
    qn(t);
  }
  function Yu(e) {
    var t = e.firstChild;
    for (t && t.nodeType === 10 && (t = t.nextSibling); t; ) {
      var a = t;
      switch (((t = t.nextSibling), a.nodeName)) {
        case "HTML":
        case "HEAD":
        case "BODY":
          Yu(a), Zi(a);
          continue;
        case "SCRIPT":
        case "STYLE":
          continue;
        case "LINK":
          if (a.rel.toLowerCase() === "stylesheet") continue;
      }
      e.removeChild(a);
    }
  }
  function Bp(e, t, a, l) {
    for (; e.nodeType === 1; ) {
      var n = a;
      if (e.nodeName.toLowerCase() !== t.toLowerCase()) {
        if (!l && (e.nodeName !== "INPUT" || e.type !== "hidden")) break;
      } else if (l) {
        if (!e[Zl])
          switch (t) {
            case "meta":
              if (!e.hasAttribute("itemprop")) break;
              return e;
            case "link":
              if (
                ((i = e.getAttribute("rel")),
                i === "stylesheet" && e.hasAttribute("data-precedence"))
              )
                break;
              if (
                i !== n.rel ||
                e.getAttribute("href") !==
                  (n.href == null || n.href === "" ? null : n.href) ||
                e.getAttribute("crossorigin") !==
                  (n.crossOrigin == null ? null : n.crossOrigin) ||
                e.getAttribute("title") !== (n.title == null ? null : n.title)
              )
                break;
              return e;
            case "style":
              if (e.hasAttribute("data-precedence")) break;
              return e;
            case "script":
              if (
                ((i = e.getAttribute("src")),
                (i !== (n.src == null ? null : n.src) ||
                  e.getAttribute("type") !== (n.type == null ? null : n.type) ||
                  e.getAttribute("crossorigin") !==
                    (n.crossOrigin == null ? null : n.crossOrigin)) &&
                  i &&
                  e.hasAttribute("async") &&
                  !e.hasAttribute("itemprop"))
              )
                break;
              return e;
            default:
              return e;
          }
      } else if (t === "input" && e.type === "hidden") {
        var i = n.name == null ? null : "" + n.name;
        if (n.type === "hidden" && e.getAttribute("name") === i) return e;
      } else return e;
      if (((e = Ot(e.nextSibling)), e === null)) break;
    }
    return null;
  }
  function Hp(e, t, a) {
    if (t === "") return null;
    for (; e.nodeType !== 3; )
      if (
        ((e.nodeType !== 1 || e.nodeName !== "INPUT" || e.type !== "hidden") &&
          !a) ||
        ((e = Ot(e.nextSibling)), e === null)
      )
        return null;
    return e;
  }
  function Vu(e) {
    return (
      e.data === "$!" ||
      (e.data === "$?" && e.ownerDocument.readyState === "complete")
    );
  }
  function qp(e, t) {
    var a = e.ownerDocument;
    if (e.data !== "$?" || a.readyState === "complete") t();
    else {
      var l = function () {
        t(), a.removeEventListener("DOMContentLoaded", l);
      };
      a.addEventListener("DOMContentLoaded", l), (e._reactRetry = l);
    }
  }
  function Ot(e) {
    for (; e != null; e = e.nextSibling) {
      var t = e.nodeType;
      if (t === 1 || t === 3) break;
      if (t === 8) {
        if (
          ((t = e.data),
          t === "$" || t === "$!" || t === "$?" || t === "F!" || t === "F")
        )
          break;
        if (t === "/$") return null;
      }
    }
    return e;
  }
  var Gu = null;
  function tm(e) {
    e = e.previousSibling;
    for (var t = 0; e; ) {
      if (e.nodeType === 8) {
        var a = e.data;
        if (a === "$" || a === "$!" || a === "$?") {
          if (t === 0) return e;
          t--;
        } else a === "/$" && t++;
      }
      e = e.previousSibling;
    }
    return null;
  }
  function am(e, t, a) {
    switch (((t = ai(a)), e)) {
      case "html":
        if (((e = t.documentElement), !e)) throw Error(c(452));
        return e;
      case "head":
        if (((e = t.head), !e)) throw Error(c(453));
        return e;
      case "body":
        if (((e = t.body), !e)) throw Error(c(454));
        return e;
      default:
        throw Error(c(451));
    }
  }
  function Mn(e) {
    for (var t = e.attributes; t.length; ) e.removeAttributeNode(t[0]);
    Zi(e);
  }
  var At = new Map(),
    lm = new Set();
  function li(e) {
    return typeof e.getRootNode == "function"
      ? e.getRootNode()
      : e.nodeType === 9
      ? e
      : e.ownerDocument;
  }
  var aa = H.d;
  H.d = { f: Yp, r: Vp, D: Gp, C: Xp, L: Qp, m: Zp, X: Jp, S: Kp, M: $p };
  function Yp() {
    var e = aa.f(),
      t = Js();
    return e || t;
  }
  function Vp(e) {
    var t = al(e);
    t !== null && t.tag === 5 && t.type === "form" ? Nf(t) : aa.r(e);
  }
  var kl = typeof document > "u" ? null : document;
  function nm(e, t, a) {
    var l = kl;
    if (l && typeof t == "string" && t) {
      var n = jt(t);
      (n = 'link[rel="' + e + '"][href="' + n + '"]'),
        typeof a == "string" && (n += '[crossorigin="' + a + '"]'),
        lm.has(n) ||
          (lm.add(n),
          (e = { rel: e, crossOrigin: a, href: t }),
          l.querySelector(n) === null &&
            ((t = l.createElement("link")),
            Fe(t, "link", e),
            Xe(t),
            l.head.appendChild(t)));
    }
  }
  function Gp(e) {
    aa.D(e), nm("dns-prefetch", e, null);
  }
  function Xp(e, t) {
    aa.C(e, t), nm("preconnect", e, t);
  }
  function Qp(e, t, a) {
    aa.L(e, t, a);
    var l = kl;
    if (l && e && t) {
      var n = 'link[rel="preload"][as="' + jt(t) + '"]';
      t === "image" && a && a.imageSrcSet
        ? ((n += '[imagesrcset="' + jt(a.imageSrcSet) + '"]'),
          typeof a.imageSizes == "string" &&
            (n += '[imagesizes="' + jt(a.imageSizes) + '"]'))
        : (n += '[href="' + jt(e) + '"]');
      var i = n;
      switch (t) {
        case "style":
          i = Ll(e);
          break;
        case "script":
          i = Bl(e);
      }
      At.has(i) ||
        ((e = v(
          {
            rel: "preload",
            href: t === "image" && a && a.imageSrcSet ? void 0 : e,
            as: t,
          },
          a
        )),
        At.set(i, e),
        l.querySelector(n) !== null ||
          (t === "style" && l.querySelector(Dn(i))) ||
          (t === "script" && l.querySelector(zn(i))) ||
          ((t = l.createElement("link")),
          Fe(t, "link", e),
          Xe(t),
          l.head.appendChild(t)));
    }
  }
  function Zp(e, t) {
    aa.m(e, t);
    var a = kl;
    if (a && e) {
      var l = t && typeof t.as == "string" ? t.as : "script",
        n =
          'link[rel="modulepreload"][as="' + jt(l) + '"][href="' + jt(e) + '"]',
        i = n;
      switch (l) {
        case "audioworklet":
        case "paintworklet":
        case "serviceworker":
        case "sharedworker":
        case "worker":
        case "script":
          i = Bl(e);
      }
      if (
        !At.has(i) &&
        ((e = v({ rel: "modulepreload", href: e }, t)),
        At.set(i, e),
        a.querySelector(n) === null)
      ) {
        switch (l) {
          case "audioworklet":
          case "paintworklet":
          case "serviceworker":
          case "sharedworker":
          case "worker":
          case "script":
            if (a.querySelector(zn(i))) return;
        }
        (l = a.createElement("link")),
          Fe(l, "link", e),
          Xe(l),
          a.head.appendChild(l);
      }
    }
  }
  function Kp(e, t, a) {
    aa.S(e, t, a);
    var l = kl;
    if (l && e) {
      var n = ll(l).hoistableStyles,
        i = Ll(e);
      t = t || "default";
      var d = n.get(i);
      if (!d) {
        var h = { loading: 0, preload: null };
        if ((d = l.querySelector(Dn(i)))) h.loading = 5;
        else {
          (e = v({ rel: "stylesheet", href: e, "data-precedence": t }, a)),
            (a = At.get(i)) && Xu(e, a);
          var j = (d = l.createElement("link"));
          Xe(j),
            Fe(j, "link", e),
            (j._p = new Promise(function (O, V) {
              (j.onload = O), (j.onerror = V);
            })),
            j.addEventListener("load", function () {
              h.loading |= 1;
            }),
            j.addEventListener("error", function () {
              h.loading |= 2;
            }),
            (h.loading |= 4),
            ni(d, t, l);
        }
        (d = { type: "stylesheet", instance: d, count: 1, state: h }),
          n.set(i, d);
      }
    }
  }
  function Jp(e, t) {
    aa.X(e, t);
    var a = kl;
    if (a && e) {
      var l = ll(a).hoistableScripts,
        n = Bl(e),
        i = l.get(n);
      i ||
        ((i = a.querySelector(zn(n))),
        i ||
          ((e = v({ src: e, async: !0 }, t)),
          (t = At.get(n)) && Qu(e, t),
          (i = a.createElement("script")),
          Xe(i),
          Fe(i, "link", e),
          a.head.appendChild(i)),
        (i = { type: "script", instance: i, count: 1, state: null }),
        l.set(n, i));
    }
  }
  function $p(e, t) {
    aa.M(e, t);
    var a = kl;
    if (a && e) {
      var l = ll(a).hoistableScripts,
        n = Bl(e),
        i = l.get(n);
      i ||
        ((i = a.querySelector(zn(n))),
        i ||
          ((e = v({ src: e, async: !0, type: "module" }, t)),
          (t = At.get(n)) && Qu(e, t),
          (i = a.createElement("script")),
          Xe(i),
          Fe(i, "link", e),
          a.head.appendChild(i)),
        (i = { type: "script", instance: i, count: 1, state: null }),
        l.set(n, i));
    }
  }
  function sm(e, t, a, l) {
    var n = (n = te.current) ? li(n) : null;
    if (!n) throw Error(c(446));
    switch (e) {
      case "meta":
      case "title":
        return null;
      case "style":
        return typeof a.precedence == "string" && typeof a.href == "string"
          ? ((t = Ll(a.href)),
            (a = ll(n).hoistableStyles),
            (l = a.get(t)),
            l ||
              ((l = { type: "style", instance: null, count: 0, state: null }),
              a.set(t, l)),
            l)
          : { type: "void", instance: null, count: 0, state: null };
      case "link":
        if (
          a.rel === "stylesheet" &&
          typeof a.href == "string" &&
          typeof a.precedence == "string"
        ) {
          e = Ll(a.href);
          var i = ll(n).hoistableStyles,
            d = i.get(e);
          if (
            (d ||
              ((n = n.ownerDocument || n),
              (d = {
                type: "stylesheet",
                instance: null,
                count: 0,
                state: { loading: 0, preload: null },
              }),
              i.set(e, d),
              (i = n.querySelector(Dn(e))) &&
                !i._p &&
                ((d.instance = i), (d.state.loading = 5)),
              At.has(e) ||
                ((a = {
                  rel: "preload",
                  as: "style",
                  href: a.href,
                  crossOrigin: a.crossOrigin,
                  integrity: a.integrity,
                  media: a.media,
                  hrefLang: a.hrefLang,
                  referrerPolicy: a.referrerPolicy,
                }),
                At.set(e, a),
                i || Fp(n, e, a, d.state))),
            t && l === null)
          )
            throw Error(c(528, ""));
          return d;
        }
        if (t && l !== null) throw Error(c(529, ""));
        return null;
      case "script":
        return (
          (t = a.async),
          (a = a.src),
          typeof a == "string" &&
          t &&
          typeof t != "function" &&
          typeof t != "symbol"
            ? ((t = Bl(a)),
              (a = ll(n).hoistableScripts),
              (l = a.get(t)),
              l ||
                ((l = {
                  type: "script",
                  instance: null,
                  count: 0,
                  state: null,
                }),
                a.set(t, l)),
              l)
            : { type: "void", instance: null, count: 0, state: null }
        );
      default:
        throw Error(c(444, e));
    }
  }
  function Ll(e) {
    return 'href="' + jt(e) + '"';
  }
  function Dn(e) {
    return 'link[rel="stylesheet"][' + e + "]";
  }
  function im(e) {
    return v({}, e, { "data-precedence": e.precedence, precedence: null });
  }
  function Fp(e, t, a, l) {
    e.querySelector('link[rel="preload"][as="style"][' + t + "]")
      ? (l.loading = 1)
      : ((t = e.createElement("link")),
        (l.preload = t),
        t.addEventListener("load", function () {
          return (l.loading |= 1);
        }),
        t.addEventListener("error", function () {
          return (l.loading |= 2);
        }),
        Fe(t, "link", a),
        Xe(t),
        e.head.appendChild(t));
  }
  function Bl(e) {
    return '[src="' + jt(e) + '"]';
  }
  function zn(e) {
    return "script[async]" + e;
  }
  function rm(e, t, a) {
    if ((t.count++, t.instance === null))
      switch (t.type) {
        case "style":
          var l = e.querySelector('style[data-href~="' + jt(a.href) + '"]');
          if (l) return (t.instance = l), Xe(l), l;
          var n = v({}, a, {
            "data-href": a.href,
            "data-precedence": a.precedence,
            href: null,
            precedence: null,
          });
          return (
            (l = (e.ownerDocument || e).createElement("style")),
            Xe(l),
            Fe(l, "style", n),
            ni(l, a.precedence, e),
            (t.instance = l)
          );
        case "stylesheet":
          n = Ll(a.href);
          var i = e.querySelector(Dn(n));
          if (i) return (t.state.loading |= 4), (t.instance = i), Xe(i), i;
          (l = im(a)),
            (n = At.get(n)) && Xu(l, n),
            (i = (e.ownerDocument || e).createElement("link")),
            Xe(i);
          var d = i;
          return (
            (d._p = new Promise(function (h, j) {
              (d.onload = h), (d.onerror = j);
            })),
            Fe(i, "link", l),
            (t.state.loading |= 4),
            ni(i, a.precedence, e),
            (t.instance = i)
          );
        case "script":
          return (
            (i = Bl(a.src)),
            (n = e.querySelector(zn(i)))
              ? ((t.instance = n), Xe(n), n)
              : ((l = a),
                (n = At.get(i)) && ((l = v({}, a)), Qu(l, n)),
                (e = e.ownerDocument || e),
                (n = e.createElement("script")),
                Xe(n),
                Fe(n, "link", l),
                e.head.appendChild(n),
                (t.instance = n))
          );
        case "void":
          return null;
        default:
          throw Error(c(443, t.type));
      }
    else
      t.type === "stylesheet" &&
        (t.state.loading & 4) === 0 &&
        ((l = t.instance), (t.state.loading |= 4), ni(l, a.precedence, e));
    return t.instance;
  }
  function ni(e, t, a) {
    for (
      var l = a.querySelectorAll(
          'link[rel="stylesheet"][data-precedence],style[data-precedence]'
        ),
        n = l.length ? l[l.length - 1] : null,
        i = n,
        d = 0;
      d < l.length;
      d++
    ) {
      var h = l[d];
      if (h.dataset.precedence === t) i = h;
      else if (i !== n) break;
    }
    i
      ? i.parentNode.insertBefore(e, i.nextSibling)
      : ((t = a.nodeType === 9 ? a.head : a), t.insertBefore(e, t.firstChild));
  }
  function Xu(e, t) {
    e.crossOrigin == null && (e.crossOrigin = t.crossOrigin),
      e.referrerPolicy == null && (e.referrerPolicy = t.referrerPolicy),
      e.title == null && (e.title = t.title);
  }
  function Qu(e, t) {
    e.crossOrigin == null && (e.crossOrigin = t.crossOrigin),
      e.referrerPolicy == null && (e.referrerPolicy = t.referrerPolicy),
      e.integrity == null && (e.integrity = t.integrity);
  }
  var si = null;
  function um(e, t, a) {
    if (si === null) {
      var l = new Map(),
        n = (si = new Map());
      n.set(a, l);
    } else (n = si), (l = n.get(a)), l || ((l = new Map()), n.set(a, l));
    if (l.has(e)) return l;
    for (
      l.set(e, null), a = a.getElementsByTagName(e), n = 0;
      n < a.length;
      n++
    ) {
      var i = a[n];
      if (
        !(
          i[Zl] ||
          i[We] ||
          (e === "link" && i.getAttribute("rel") === "stylesheet")
        ) &&
        i.namespaceURI !== "http://www.w3.org/2000/svg"
      ) {
        var d = i.getAttribute(t) || "";
        d = e + d;
        var h = l.get(d);
        h ? h.push(i) : l.set(d, [i]);
      }
    }
    return l;
  }
  function cm(e, t, a) {
    (e = e.ownerDocument || e),
      e.head.insertBefore(
        a,
        t === "title" ? e.querySelector("head > title") : null
      );
  }
  function Wp(e, t, a) {
    if (a === 1 || t.itemProp != null) return !1;
    switch (e) {
      case "meta":
      case "title":
        return !0;
      case "style":
        if (
          typeof t.precedence != "string" ||
          typeof t.href != "string" ||
          t.href === ""
        )
          break;
        return !0;
      case "link":
        if (
          typeof t.rel != "string" ||
          typeof t.href != "string" ||
          t.href === "" ||
          t.onLoad ||
          t.onError
        )
          break;
        switch (t.rel) {
          case "stylesheet":
            return (
              (e = t.disabled), typeof t.precedence == "string" && e == null
            );
          default:
            return !0;
        }
      case "script":
        if (
          t.async &&
          typeof t.async != "function" &&
          typeof t.async != "symbol" &&
          !t.onLoad &&
          !t.onError &&
          t.src &&
          typeof t.src == "string"
        )
          return !0;
    }
    return !1;
  }
  function om(e) {
    return !(e.type === "stylesheet" && (e.state.loading & 3) === 0);
  }
  var Un = null;
  function Pp() {}
  function Ip(e, t, a) {
    if (Un === null) throw Error(c(475));
    var l = Un;
    if (
      t.type === "stylesheet" &&
      (typeof a.media != "string" || matchMedia(a.media).matches !== !1) &&
      (t.state.loading & 4) === 0
    ) {
      if (t.instance === null) {
        var n = Ll(a.href),
          i = e.querySelector(Dn(n));
        if (i) {
          (e = i._p),
            e !== null &&
              typeof e == "object" &&
              typeof e.then == "function" &&
              (l.count++, (l = ii.bind(l)), e.then(l, l)),
            (t.state.loading |= 4),
            (t.instance = i),
            Xe(i);
          return;
        }
        (i = e.ownerDocument || e),
          (a = im(a)),
          (n = At.get(n)) && Xu(a, n),
          (i = i.createElement("link")),
          Xe(i);
        var d = i;
        (d._p = new Promise(function (h, j) {
          (d.onload = h), (d.onerror = j);
        })),
          Fe(i, "link", a),
          (t.instance = i);
      }
      l.stylesheets === null && (l.stylesheets = new Map()),
        l.stylesheets.set(t, e),
        (e = t.state.preload) &&
          (t.state.loading & 3) === 0 &&
          (l.count++,
          (t = ii.bind(l)),
          e.addEventListener("load", t),
          e.addEventListener("error", t));
    }
  }
  function ex() {
    if (Un === null) throw Error(c(475));
    var e = Un;
    return (
      e.stylesheets && e.count === 0 && Zu(e, e.stylesheets),
      0 < e.count
        ? function (t) {
            var a = setTimeout(function () {
              if ((e.stylesheets && Zu(e, e.stylesheets), e.unsuspend)) {
                var l = e.unsuspend;
                (e.unsuspend = null), l();
              }
            }, 6e4);
            return (
              (e.unsuspend = t),
              function () {
                (e.unsuspend = null), clearTimeout(a);
              }
            );
          }
        : null
    );
  }
  function ii() {
    if ((this.count--, this.count === 0)) {
      if (this.stylesheets) Zu(this, this.stylesheets);
      else if (this.unsuspend) {
        var e = this.unsuspend;
        (this.unsuspend = null), e();
      }
    }
  }
  var ri = null;
  function Zu(e, t) {
    (e.stylesheets = null),
      e.unsuspend !== null &&
        (e.count++,
        (ri = new Map()),
        t.forEach(tx, e),
        (ri = null),
        ii.call(e));
  }
  function tx(e, t) {
    if (!(t.state.loading & 4)) {
      var a = ri.get(e);
      if (a) var l = a.get(null);
      else {
        (a = new Map()), ri.set(e, a);
        for (
          var n = e.querySelectorAll(
              "link[data-precedence],style[data-precedence]"
            ),
            i = 0;
          i < n.length;
          i++
        ) {
          var d = n[i];
          (d.nodeName === "LINK" || d.getAttribute("media") !== "not all") &&
            (a.set(d.dataset.precedence, d), (l = d));
        }
        l && a.set(null, l);
      }
      (n = t.instance),
        (d = n.getAttribute("data-precedence")),
        (i = a.get(d) || l),
        i === l && a.set(null, n),
        a.set(d, n),
        this.count++,
        (l = ii.bind(this)),
        n.addEventListener("load", l),
        n.addEventListener("error", l),
        i
          ? i.parentNode.insertBefore(n, i.nextSibling)
          : ((e = e.nodeType === 9 ? e.head : e),
            e.insertBefore(n, e.firstChild)),
        (t.state.loading |= 4);
    }
  }
  var kn = {
    $$typeof: U,
    Provider: null,
    Consumer: null,
    _currentValue: $,
    _currentValue2: $,
    _threadCount: 0,
  };
  function ax(e, t, a, l, n, i, d, h) {
    (this.tag = 1),
      (this.containerInfo = e),
      (this.pingCache = this.current = this.pendingChildren = null),
      (this.timeoutHandle = -1),
      (this.callbackNode =
        this.next =
        this.pendingContext =
        this.context =
        this.cancelPendingCommit =
          null),
      (this.callbackPriority = 0),
      (this.expirationTimes = Vi(-1)),
      (this.entangledLanes =
        this.shellSuspendCounter =
        this.errorRecoveryDisabledLanes =
        this.expiredLanes =
        this.warmLanes =
        this.pingedLanes =
        this.suspendedLanes =
        this.pendingLanes =
          0),
      (this.entanglements = Vi(0)),
      (this.hiddenUpdates = Vi(null)),
      (this.identifierPrefix = l),
      (this.onUncaughtError = n),
      (this.onCaughtError = i),
      (this.onRecoverableError = d),
      (this.pooledCache = null),
      (this.pooledCacheLanes = 0),
      (this.formState = h),
      (this.incompleteTransitions = new Map());
  }
  function fm(e, t, a, l, n, i, d, h, j, O, V, X) {
    return (
      (e = new ax(e, t, a, d, h, j, O, X)),
      (t = 1),
      i === !0 && (t |= 24),
      (i = mt(3, null, null, t)),
      (e.current = i),
      (i.stateNode = e),
      (t = _r()),
      t.refCount++,
      (e.pooledCache = t),
      t.refCount++,
      (i.memoizedState = { element: l, isDehydrated: a, cache: t }),
      Or(i),
      e
    );
  }
  function dm(e) {
    return e ? ((e = pl), e) : pl;
  }
  function mm(e, t, a, l, n, i) {
    (n = dm(n)),
      l.context === null ? (l.context = n) : (l.pendingContext = n),
      (l = da(t)),
      (l.payload = { element: a }),
      (i = i === void 0 ? null : i),
      i !== null && (l.callback = i),
      (a = ma(e, l, t)),
      a !== null && (yt(a, e, t), dn(a, e, t));
  }
  function hm(e, t) {
    if (((e = e.memoizedState), e !== null && e.dehydrated !== null)) {
      var a = e.retryLane;
      e.retryLane = a !== 0 && a < t ? a : t;
    }
  }
  function Ku(e, t) {
    hm(e, t), (e = e.alternate) && hm(e, t);
  }
  function pm(e) {
    if (e.tag === 13) {
      var t = hl(e, 67108864);
      t !== null && yt(t, e, 67108864), Ku(e, 67108864);
    }
  }
  var ui = !0;
  function lx(e, t, a, l) {
    var n = S.T;
    S.T = null;
    var i = H.p;
    try {
      (H.p = 2), Ju(e, t, a, l);
    } finally {
      (H.p = i), (S.T = n);
    }
  }
  function nx(e, t, a, l) {
    var n = S.T;
    S.T = null;
    var i = H.p;
    try {
      (H.p = 8), Ju(e, t, a, l);
    } finally {
      (H.p = i), (S.T = n);
    }
  }
  function Ju(e, t, a, l) {
    if (ui) {
      var n = $u(l);
      if (n === null) Uu(e, t, l, ci, a), gm(e, l);
      else if (ix(n, e, t, a, l)) l.stopPropagation();
      else if ((gm(e, l), t & 4 && -1 < sx.indexOf(e))) {
        for (; n !== null; ) {
          var i = al(n);
          if (i !== null)
            switch (i.tag) {
              case 3:
                if (((i = i.stateNode), i.current.memoizedState.isDehydrated)) {
                  var d = Ma(i.pendingLanes);
                  if (d !== 0) {
                    var h = i;
                    for (h.pendingLanes |= 2, h.entangledLanes |= 2; d; ) {
                      var j = 1 << (31 - ft(d));
                      (h.entanglements[1] |= j), (d &= ~j);
                    }
                    Bt(i), (Ne & 6) === 0 && ((Zs = Dt() + 500), Rn(0));
                  }
                }
                break;
              case 13:
                (h = hl(i, 2)), h !== null && yt(h, i, 2), Js(), Ku(i, 2);
            }
          if (((i = $u(l)), i === null && Uu(e, t, l, ci, a), i === n)) break;
          n = i;
        }
        n !== null && l.stopPropagation();
      } else Uu(e, t, l, null, a);
    }
  }
  function $u(e) {
    return (e = er(e)), Fu(e);
  }
  var ci = null;
  function Fu(e) {
    if (((ci = null), (e = tl(e)), e !== null)) {
      var t = m(e);
      if (t === null) e = null;
      else {
        var a = t.tag;
        if (a === 13) {
          if (((e = x(t)), e !== null)) return e;
          e = null;
        } else if (a === 3) {
          if (t.stateNode.current.memoizedState.isDehydrated)
            return t.tag === 3 ? t.stateNode.containerInfo : null;
          e = null;
        } else t !== e && (e = null);
      }
    }
    return (ci = e), null;
  }
  function xm(e) {
    switch (e) {
      case "beforetoggle":
      case "cancel":
      case "click":
      case "close":
      case "contextmenu":
      case "copy":
      case "cut":
      case "auxclick":
      case "dblclick":
      case "dragend":
      case "dragstart":
      case "drop":
      case "focusin":
      case "focusout":
      case "input":
      case "invalid":
      case "keydown":
      case "keypress":
      case "keyup":
      case "mousedown":
      case "mouseup":
      case "paste":
      case "pause":
      case "play":
      case "pointercancel":
      case "pointerdown":
      case "pointerup":
      case "ratechange":
      case "reset":
      case "resize":
      case "seeked":
      case "submit":
      case "toggle":
      case "touchcancel":
      case "touchend":
      case "touchstart":
      case "volumechange":
      case "change":
      case "selectionchange":
      case "textInput":
      case "compositionstart":
      case "compositionend":
      case "compositionupdate":
      case "beforeblur":
      case "afterblur":
      case "beforeinput":
      case "blur":
      case "fullscreenchange":
      case "focus":
      case "hashchange":
      case "popstate":
      case "select":
      case "selectstart":
        return 2;
      case "drag":
      case "dragenter":
      case "dragexit":
      case "dragleave":
      case "dragover":
      case "mousemove":
      case "mouseout":
      case "mouseover":
      case "pointermove":
      case "pointerout":
      case "pointerover":
      case "scroll":
      case "touchmove":
      case "wheel":
      case "mouseenter":
      case "mouseleave":
      case "pointerenter":
      case "pointerleave":
        return 8;
      case "message":
        switch (Xh()) {
          case Ac:
            return 2;
          case Rc:
            return 8;
          case ts:
          case Qh:
            return 32;
          case Cc:
            return 268435456;
          default:
            return 32;
        }
      default:
        return 32;
    }
  }
  var Wu = !1,
    Ta = null,
    _a = null,
    Aa = null,
    Ln = new Map(),
    Bn = new Map(),
    Ra = [],
    sx =
      "mousedown mouseup touchcancel touchend touchstart auxclick dblclick pointercancel pointerdown pointerup dragend dragstart drop compositionend compositionstart keydown keypress keyup input textInput copy cut paste click change contextmenu reset".split(
        " "
      );
  function gm(e, t) {
    switch (e) {
      case "focusin":
      case "focusout":
        Ta = null;
        break;
      case "dragenter":
      case "dragleave":
        _a = null;
        break;
      case "mouseover":
      case "mouseout":
        Aa = null;
        break;
      case "pointerover":
      case "pointerout":
        Ln.delete(t.pointerId);
        break;
      case "gotpointercapture":
      case "lostpointercapture":
        Bn.delete(t.pointerId);
    }
  }
  function Hn(e, t, a, l, n, i) {
    return e === null || e.nativeEvent !== i
      ? ((e = {
          blockedOn: t,
          domEventName: a,
          eventSystemFlags: l,
          nativeEvent: i,
          targetContainers: [n],
        }),
        t !== null && ((t = al(t)), t !== null && pm(t)),
        e)
      : ((e.eventSystemFlags |= l),
        (t = e.targetContainers),
        n !== null && t.indexOf(n) === -1 && t.push(n),
        e);
  }
  function ix(e, t, a, l, n) {
    switch (t) {
      case "focusin":
        return (Ta = Hn(Ta, e, t, a, l, n)), !0;
      case "dragenter":
        return (_a = Hn(_a, e, t, a, l, n)), !0;
      case "mouseover":
        return (Aa = Hn(Aa, e, t, a, l, n)), !0;
      case "pointerover":
        var i = n.pointerId;
        return Ln.set(i, Hn(Ln.get(i) || null, e, t, a, l, n)), !0;
      case "gotpointercapture":
        return (
          (i = n.pointerId), Bn.set(i, Hn(Bn.get(i) || null, e, t, a, l, n)), !0
        );
    }
    return !1;
  }
  function ym(e) {
    var t = tl(e.target);
    if (t !== null) {
      var a = m(t);
      if (a !== null) {
        if (((t = a.tag), t === 13)) {
          if (((t = x(a)), t !== null)) {
            (e.blockedOn = t),
              Ih(e.priority, function () {
                if (a.tag === 13) {
                  var l = gt();
                  l = Gi(l);
                  var n = hl(a, l);
                  n !== null && yt(n, a, l), Ku(a, l);
                }
              });
            return;
          }
        } else if (t === 3 && a.stateNode.current.memoizedState.isDehydrated) {
          e.blockedOn = a.tag === 3 ? a.stateNode.containerInfo : null;
          return;
        }
      }
    }
    e.blockedOn = null;
  }
  function oi(e) {
    if (e.blockedOn !== null) return !1;
    for (var t = e.targetContainers; 0 < t.length; ) {
      var a = $u(e.nativeEvent);
      if (a === null) {
        a = e.nativeEvent;
        var l = new a.constructor(a.type, a);
        (Ii = l), a.target.dispatchEvent(l), (Ii = null);
      } else return (t = al(a)), t !== null && pm(t), (e.blockedOn = a), !1;
      t.shift();
    }
    return !0;
  }
  function bm(e, t, a) {
    oi(e) && a.delete(t);
  }
  function rx() {
    (Wu = !1),
      Ta !== null && oi(Ta) && (Ta = null),
      _a !== null && oi(_a) && (_a = null),
      Aa !== null && oi(Aa) && (Aa = null),
      Ln.forEach(bm),
      Bn.forEach(bm);
  }
  function fi(e, t) {
    e.blockedOn === t &&
      ((e.blockedOn = null),
      Wu ||
        ((Wu = !0),
        s.unstable_scheduleCallback(s.unstable_NormalPriority, rx)));
  }
  var di = null;
  function vm(e) {
    di !== e &&
      ((di = e),
      s.unstable_scheduleCallback(s.unstable_NormalPriority, function () {
        di === e && (di = null);
        for (var t = 0; t < e.length; t += 3) {
          var a = e[t],
            l = e[t + 1],
            n = e[t + 2];
          if (typeof l != "function") {
            if (Fu(l || a) === null) continue;
            break;
          }
          var i = al(a);
          i !== null &&
            (e.splice(t, 3),
            (t -= 3),
            Fr(i, { pending: !0, data: n, method: a.method, action: l }, l, n));
        }
      }));
  }
  function qn(e) {
    function t(j) {
      return fi(j, e);
    }
    Ta !== null && fi(Ta, e),
      _a !== null && fi(_a, e),
      Aa !== null && fi(Aa, e),
      Ln.forEach(t),
      Bn.forEach(t);
    for (var a = 0; a < Ra.length; a++) {
      var l = Ra[a];
      l.blockedOn === e && (l.blockedOn = null);
    }
    for (; 0 < Ra.length && ((a = Ra[0]), a.blockedOn === null); )
      ym(a), a.blockedOn === null && Ra.shift();
    if (((a = (e.ownerDocument || e).$$reactFormReplay), a != null))
      for (l = 0; l < a.length; l += 3) {
        var n = a[l],
          i = a[l + 1],
          d = n[lt] || null;
        if (typeof i == "function") d || vm(a);
        else if (d) {
          var h = null;
          if (i && i.hasAttribute("formAction")) {
            if (((n = i), (d = i[lt] || null))) h = d.formAction;
            else if (Fu(n) !== null) continue;
          } else h = d.action;
          typeof h == "function" ? (a[l + 1] = h) : (a.splice(l, 3), (l -= 3)),
            vm(a);
        }
      }
  }
  function Pu(e) {
    this._internalRoot = e;
  }
  (mi.prototype.render = Pu.prototype.render =
    function (e) {
      var t = this._internalRoot;
      if (t === null) throw Error(c(409));
      var a = t.current,
        l = gt();
      mm(a, l, e, t, null, null);
    }),
    (mi.prototype.unmount = Pu.prototype.unmount =
      function () {
        var e = this._internalRoot;
        if (e !== null) {
          this._internalRoot = null;
          var t = e.containerInfo;
          mm(e.current, 2, null, e, null, null), Js(), (t[el] = null);
        }
      });
  function mi(e) {
    this._internalRoot = e;
  }
  mi.prototype.unstable_scheduleHydration = function (e) {
    if (e) {
      var t = Uc();
      e = { blockedOn: null, target: e, priority: t };
      for (var a = 0; a < Ra.length && t !== 0 && t < Ra[a].priority; a++);
      Ra.splice(a, 0, e), a === 0 && ym(e);
    }
  };
  var jm = u.version;
  if (jm !== "19.1.0") throw Error(c(527, jm, "19.1.0"));
  H.findDOMNode = function (e) {
    var t = e._reactInternals;
    if (t === void 0)
      throw typeof e.render == "function"
        ? Error(c(188))
        : ((e = Object.keys(e).join(",")), Error(c(268, e)));
    return (
      (e = y(t)),
      (e = e !== null ? p(e) : null),
      (e = e === null ? null : e.stateNode),
      e
    );
  };
  var ux = {
    bundleType: 0,
    version: "19.1.0",
    rendererPackageName: "react-dom",
    currentDispatcherRef: S,
    reconcilerVersion: "19.1.0",
  };
  if (typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ < "u") {
    var hi = __REACT_DEVTOOLS_GLOBAL_HOOK__;
    if (!hi.isDisabled && hi.supportsFiber)
      try {
        (Gl = hi.inject(ux)), (ot = hi);
      } catch {}
  }
  return (
    (Vn.createRoot = function (e, t) {
      if (!f(e)) throw Error(c(299));
      var a = !1,
        l = "",
        n = Lf,
        i = Bf,
        d = Hf,
        h = null;
      return (
        t != null &&
          (t.unstable_strictMode === !0 && (a = !0),
          t.identifierPrefix !== void 0 && (l = t.identifierPrefix),
          t.onUncaughtError !== void 0 && (n = t.onUncaughtError),
          t.onCaughtError !== void 0 && (i = t.onCaughtError),
          t.onRecoverableError !== void 0 && (d = t.onRecoverableError),
          t.unstable_transitionCallbacks !== void 0 &&
            (h = t.unstable_transitionCallbacks)),
        (t = fm(e, 1, !1, null, null, a, l, n, i, d, h, null)),
        (e[el] = t.current),
        zu(e),
        new Pu(t)
      );
    }),
    (Vn.hydrateRoot = function (e, t, a) {
      if (!f(e)) throw Error(c(299));
      var l = !1,
        n = "",
        i = Lf,
        d = Bf,
        h = Hf,
        j = null,
        O = null;
      return (
        a != null &&
          (a.unstable_strictMode === !0 && (l = !0),
          a.identifierPrefix !== void 0 && (n = a.identifierPrefix),
          a.onUncaughtError !== void 0 && (i = a.onUncaughtError),
          a.onCaughtError !== void 0 && (d = a.onCaughtError),
          a.onRecoverableError !== void 0 && (h = a.onRecoverableError),
          a.unstable_transitionCallbacks !== void 0 &&
            (j = a.unstable_transitionCallbacks),
          a.formState !== void 0 && (O = a.formState)),
        (t = fm(e, 1, !0, t, a ?? null, l, n, i, d, h, j, O)),
        (t.context = dm(null)),
        (a = t.current),
        (l = gt()),
        (l = Gi(l)),
        (n = da(l)),
        (n.callback = null),
        ma(a, n, l),
        (a = l),
        (t.current.lanes = a),
        Ql(t, a),
        Bt(t),
        (e[el] = t.current),
        zu(e),
        new mi(t)
      );
    }),
    (Vn.version = "19.1.0"),
    Vn
  );
}
var Om;
function yx() {
  if (Om) return ec.exports;
  Om = 1;
  function s() {
    if (
      !(
        typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ > "u" ||
        typeof __REACT_DEVTOOLS_GLOBAL_HOOK__.checkDCE != "function"
      )
    )
      try {
        __REACT_DEVTOOLS_GLOBAL_HOOK__.checkDCE(s);
      } catch (u) {
        console.error(u);
      }
  }
  return s(), (ec.exports = gx()), ec.exports;
}
var bx = yx(),
  T = bc(),
  Gn = {},
  Mm;
function vx() {
  if (Mm) return Gn;
  (Mm = 1),
    Object.defineProperty(Gn, "__esModule", { value: !0 }),
    (Gn.parse = x),
    (Gn.serialize = p);
  const s = /^[\u0021-\u003A\u003C\u003E-\u007E]+$/,
    u = /^[\u0021-\u003A\u003C-\u007E]*$/,
    o =
      /^([.]?[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)([.][a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i,
    c = /^[\u0020-\u003A\u003D-\u007E]*$/,
    f = Object.prototype.toString,
    m = (() => {
      const N = function () {};
      return (N.prototype = Object.create(null)), N;
    })();
  function x(N, L) {
    const w = new m(),
      C = N.length;
    if (C < 2) return w;
    const D = (L == null ? void 0 : L.decode) || v;
    let Y = 0;
    do {
      const Z = N.indexOf("=", Y);
      if (Z === -1) break;
      const U = N.indexOf(";", Y),
        q = U === -1 ? C : U;
      if (Z > q) {
        Y = N.lastIndexOf(";", Z - 1) + 1;
        continue;
      }
      const Q = b(N, Y, Z),
        W = y(N, Z, Q),
        re = N.slice(Q, W);
      if (w[re] === void 0) {
        let ue = b(N, Z + 1, q),
          ce = y(N, q, ue);
        const je = D(N.slice(ue, ce));
        w[re] = je;
      }
      Y = q + 1;
    } while (Y < C);
    return w;
  }
  function b(N, L, w) {
    do {
      const C = N.charCodeAt(L);
      if (C !== 32 && C !== 9) return L;
    } while (++L < w);
    return w;
  }
  function y(N, L, w) {
    for (; L > w; ) {
      const C = N.charCodeAt(--L);
      if (C !== 32 && C !== 9) return L + 1;
    }
    return w;
  }
  function p(N, L, w) {
    const C = (w == null ? void 0 : w.encode) || encodeURIComponent;
    if (!s.test(N)) throw new TypeError(`argument name is invalid: ${N}`);
    const D = C(L);
    if (!u.test(D)) throw new TypeError(`argument val is invalid: ${L}`);
    let Y = N + "=" + D;
    if (!w) return Y;
    if (w.maxAge !== void 0) {
      if (!Number.isInteger(w.maxAge))
        throw new TypeError(`option maxAge is invalid: ${w.maxAge}`);
      Y += "; Max-Age=" + w.maxAge;
    }
    if (w.domain) {
      if (!o.test(w.domain))
        throw new TypeError(`option domain is invalid: ${w.domain}`);
      Y += "; Domain=" + w.domain;
    }
    if (w.path) {
      if (!c.test(w.path))
        throw new TypeError(`option path is invalid: ${w.path}`);
      Y += "; Path=" + w.path;
    }
    if (w.expires) {
      if (!R(w.expires) || !Number.isFinite(w.expires.valueOf()))
        throw new TypeError(`option expires is invalid: ${w.expires}`);
      Y += "; Expires=" + w.expires.toUTCString();
    }
    if (
      (w.httpOnly && (Y += "; HttpOnly"),
      w.secure && (Y += "; Secure"),
      w.partitioned && (Y += "; Partitioned"),
      w.priority)
    )
      switch (
        typeof w.priority == "string" ? w.priority.toLowerCase() : void 0
      ) {
        case "low":
          Y += "; Priority=Low";
          break;
        case "medium":
          Y += "; Priority=Medium";
          break;
        case "high":
          Y += "; Priority=High";
          break;
        default:
          throw new TypeError(`option priority is invalid: ${w.priority}`);
      }
    if (w.sameSite)
      switch (
        typeof w.sameSite == "string" ? w.sameSite.toLowerCase() : w.sameSite
      ) {
        case !0:
        case "strict":
          Y += "; SameSite=Strict";
          break;
        case "lax":
          Y += "; SameSite=Lax";
          break;
        case "none":
          Y += "; SameSite=None";
          break;
        default:
          throw new TypeError(`option sameSite is invalid: ${w.sameSite}`);
      }
    return Y;
  }
  function v(N) {
    if (N.indexOf("%") === -1) return N;
    try {
      return decodeURIComponent(N);
    } catch {
      return N;
    }
  }
  function R(N) {
    return f.call(N) === "[object Date]";
  }
  return Gn;
}
vx();
var Dm = "popstate";
function jx(s = {}) {
  function u(c, f) {
    let { pathname: m, search: x, hash: b } = c.location;
    return fc(
      "",
      { pathname: m, search: x, hash: b },
      (f.state && f.state.usr) || null,
      (f.state && f.state.key) || "default"
    );
  }
  function o(c, f) {
    return typeof f == "string" ? f : Kn(f);
  }
  return Nx(u, o, null, s);
}
function ze(s, u) {
  if (s === !1 || s === null || typeof s > "u") throw new Error(u);
}
function qt(s, u) {
  if (!s) {
    typeof console < "u" && console.warn(u);
    try {
      throw new Error(u);
    } catch {}
  }
}
function Sx() {
  return Math.random().toString(36).substring(2, 10);
}
function zm(s, u) {
  return { usr: s.state, key: s.key, idx: u };
}
function fc(s, u, o = null, c) {
  return {
    pathname: typeof s == "string" ? s : s.pathname,
    search: "",
    hash: "",
    ...(typeof u == "string" ? Hl(u) : u),
    state: o,
    key: (u && u.key) || c || Sx(),
  };
}
function Kn({ pathname: s = "/", search: u = "", hash: o = "" }) {
  return (
    u && u !== "?" && (s += u.charAt(0) === "?" ? u : "?" + u),
    o && o !== "#" && (s += o.charAt(0) === "#" ? o : "#" + o),
    s
  );
}
function Hl(s) {
  let u = {};
  if (s) {
    let o = s.indexOf("#");
    o >= 0 && ((u.hash = s.substring(o)), (s = s.substring(0, o)));
    let c = s.indexOf("?");
    c >= 0 && ((u.search = s.substring(c)), (s = s.substring(0, c))),
      s && (u.pathname = s);
  }
  return u;
}
function Nx(s, u, o, c = {}) {
  let { window: f = document.defaultView, v5Compat: m = !1 } = c,
    x = f.history,
    b = "POP",
    y = null,
    p = v();
  p == null && ((p = 0), x.replaceState({ ...x.state, idx: p }, ""));
  function v() {
    return (x.state || { idx: null }).idx;
  }
  function R() {
    b = "POP";
    let D = v(),
      Y = D == null ? null : D - p;
    (p = D), y && y({ action: b, location: C.location, delta: Y });
  }
  function N(D, Y) {
    b = "PUSH";
    let Z = fc(C.location, D, Y);
    p = v() + 1;
    let U = zm(Z, p),
      q = C.createHref(Z);
    try {
      x.pushState(U, "", q);
    } catch (Q) {
      if (Q instanceof DOMException && Q.name === "DataCloneError") throw Q;
      f.location.assign(q);
    }
    m && y && y({ action: b, location: C.location, delta: 1 });
  }
  function L(D, Y) {
    b = "REPLACE";
    let Z = fc(C.location, D, Y);
    p = v();
    let U = zm(Z, p),
      q = C.createHref(Z);
    x.replaceState(U, "", q),
      m && y && y({ action: b, location: C.location, delta: 0 });
  }
  function w(D) {
    return wx(D);
  }
  let C = {
    get action() {
      return b;
    },
    get location() {
      return s(f, x);
    },
    listen(D) {
      if (y) throw new Error("A history only accepts one active listener");
      return (
        f.addEventListener(Dm, R),
        (y = D),
        () => {
          f.removeEventListener(Dm, R), (y = null);
        }
      );
    },
    createHref(D) {
      return u(f, D);
    },
    createURL: w,
    encodeLocation(D) {
      let Y = w(D);
      return { pathname: Y.pathname, search: Y.search, hash: Y.hash };
    },
    push: N,
    replace: L,
    go(D) {
      return x.go(D);
    },
  };
  return C;
}
function wx(s, u = !1) {
  let o = "http://localhost";
  typeof window < "u" &&
    (o =
      window.location.origin !== "null"
        ? window.location.origin
        : window.location.href),
    ze(o, "No window.location.(origin|href) available to create URL");
  let c = typeof s == "string" ? s : Kn(s);
  return (
    (c = c.replace(/ $/, "%20")),
    !u && c.startsWith("//") && (c = o + c),
    new URL(c, o)
  );
}
function eh(s, u, o = "/") {
  return Ex(s, u, o, !1);
}
function Ex(s, u, o, c) {
  let f = typeof u == "string" ? Hl(u) : u,
    m = na(f.pathname || "/", o);
  if (m == null) return null;
  let x = th(s);
  Tx(x);
  let b = null;
  for (let y = 0; b == null && y < x.length; ++y) {
    let p = Lx(m);
    b = Ux(x[y], p, c);
  }
  return b;
}
function th(s, u = [], o = [], c = "") {
  let f = (m, x, b) => {
    let y = {
      relativePath: b === void 0 ? m.path || "" : b,
      caseSensitive: m.caseSensitive === !0,
      childrenIndex: x,
      route: m,
    };
    y.relativePath.startsWith("/") &&
      (ze(
        y.relativePath.startsWith(c),
        `Absolute route path "${y.relativePath}" nested under path "${c}" is not valid. An absolute child route path must start with the combined path of all its parent routes.`
      ),
      (y.relativePath = y.relativePath.slice(c.length)));
    let p = la([c, y.relativePath]),
      v = o.concat(y);
    m.children &&
      m.children.length > 0 &&
      (ze(
        m.index !== !0,
        `Index routes must not have child routes. Please remove all child routes from route path "${p}".`
      ),
      th(m.children, u, v, p)),
      !(m.path == null && !m.index) &&
        u.push({ path: p, score: Dx(p, m.index), routesMeta: v });
  };
  return (
    s.forEach((m, x) => {
      var b;
      if (m.path === "" || !((b = m.path) != null && b.includes("?"))) f(m, x);
      else for (let y of ah(m.path)) f(m, x, y);
    }),
    u
  );
}
function ah(s) {
  let u = s.split("/");
  if (u.length === 0) return [];
  let [o, ...c] = u,
    f = o.endsWith("?"),
    m = o.replace(/\?$/, "");
  if (c.length === 0) return f ? [m, ""] : [m];
  let x = ah(c.join("/")),
    b = [];
  return (
    b.push(...x.map((y) => (y === "" ? m : [m, y].join("/")))),
    f && b.push(...x),
    b.map((y) => (s.startsWith("/") && y === "" ? "/" : y))
  );
}
function Tx(s) {
  s.sort((u, o) =>
    u.score !== o.score
      ? o.score - u.score
      : zx(
          u.routesMeta.map((c) => c.childrenIndex),
          o.routesMeta.map((c) => c.childrenIndex)
        )
  );
}
var _x = /^:[\w-]+$/,
  Ax = 3,
  Rx = 2,
  Cx = 1,
  Ox = 10,
  Mx = -2,
  Um = (s) => s === "*";
function Dx(s, u) {
  let o = s.split("/"),
    c = o.length;
  return (
    o.some(Um) && (c += Mx),
    u && (c += Rx),
    o
      .filter((f) => !Um(f))
      .reduce((f, m) => f + (_x.test(m) ? Ax : m === "" ? Cx : Ox), c)
  );
}
function zx(s, u) {
  return s.length === u.length && s.slice(0, -1).every((c, f) => c === u[f])
    ? s[s.length - 1] - u[u.length - 1]
    : 0;
}
function Ux(s, u, o = !1) {
  let { routesMeta: c } = s,
    f = {},
    m = "/",
    x = [];
  for (let b = 0; b < c.length; ++b) {
    let y = c[b],
      p = b === c.length - 1,
      v = m === "/" ? u : u.slice(m.length) || "/",
      R = Ti(
        { path: y.relativePath, caseSensitive: y.caseSensitive, end: p },
        v
      ),
      N = y.route;
    if (
      (!R &&
        p &&
        o &&
        !c[c.length - 1].route.index &&
        (R = Ti(
          { path: y.relativePath, caseSensitive: y.caseSensitive, end: !1 },
          v
        )),
      !R)
    )
      return null;
    Object.assign(f, R.params),
      x.push({
        params: f,
        pathname: la([m, R.pathname]),
        pathnameBase: Yx(la([m, R.pathnameBase])),
        route: N,
      }),
      R.pathnameBase !== "/" && (m = la([m, R.pathnameBase]));
  }
  return x;
}
function Ti(s, u) {
  typeof s == "string" && (s = { path: s, caseSensitive: !1, end: !0 });
  let [o, c] = kx(s.path, s.caseSensitive, s.end),
    f = u.match(o);
  if (!f) return null;
  let m = f[0],
    x = m.replace(/(.)\/+$/, "$1"),
    b = f.slice(1);
  return {
    params: c.reduce((p, { paramName: v, isOptional: R }, N) => {
      if (v === "*") {
        let w = b[N] || "";
        x = m.slice(0, m.length - w.length).replace(/(.)\/+$/, "$1");
      }
      const L = b[N];
      return (
        R && !L ? (p[v] = void 0) : (p[v] = (L || "").replace(/%2F/g, "/")), p
      );
    }, {}),
    pathname: m,
    pathnameBase: x,
    pattern: s,
  };
}
function kx(s, u = !1, o = !0) {
  qt(
    s === "*" || !s.endsWith("*") || s.endsWith("/*"),
    `Route path "${s}" will be treated as if it were "${s.replace(
      /\*$/,
      "/*"
    )}" because the \`*\` character must always follow a \`/\` in the pattern. To get rid of this warning, please change the route path to "${s.replace(
      /\*$/,
      "/*"
    )}".`
  );
  let c = [],
    f =
      "^" +
      s
        .replace(/\/*\*?$/, "")
        .replace(/^\/*/, "/")
        .replace(/[\\.*+^${}|()[\]]/g, "\\$&")
        .replace(
          /\/:([\w-]+)(\?)?/g,
          (x, b, y) => (
            c.push({ paramName: b, isOptional: y != null }),
            y ? "/?([^\\/]+)?" : "/([^\\/]+)"
          )
        );
  return (
    s.endsWith("*")
      ? (c.push({ paramName: "*" }),
        (f += s === "*" || s === "/*" ? "(.*)$" : "(?:\\/(.+)|\\/*)$"))
      : o
      ? (f += "\\/*$")
      : s !== "" && s !== "/" && (f += "(?:(?=\\/|$))"),
    [new RegExp(f, u ? void 0 : "i"), c]
  );
}
function Lx(s) {
  try {
    return s
      .split("/")
      .map((u) => decodeURIComponent(u).replace(/\//g, "%2F"))
      .join("/");
  } catch (u) {
    return (
      qt(
        !1,
        `The URL path "${s}" could not be decoded because it is a malformed URL segment. This is probably due to a bad percent encoding (${u}).`
      ),
      s
    );
  }
}
function na(s, u) {
  if (u === "/") return s;
  if (!s.toLowerCase().startsWith(u.toLowerCase())) return null;
  let o = u.endsWith("/") ? u.length - 1 : u.length,
    c = s.charAt(o);
  return c && c !== "/" ? null : s.slice(o) || "/";
}
function Bx(s, u = "/") {
  let {
    pathname: o,
    search: c = "",
    hash: f = "",
  } = typeof s == "string" ? Hl(s) : s;
  return {
    pathname: o ? (o.startsWith("/") ? o : Hx(o, u)) : u,
    search: Vx(c),
    hash: Gx(f),
  };
}
function Hx(s, u) {
  let o = u.replace(/\/+$/, "").split("/");
  return (
    s.split("/").forEach((f) => {
      f === ".." ? o.length > 1 && o.pop() : f !== "." && o.push(f);
    }),
    o.length > 1 ? o.join("/") : "/"
  );
}
function sc(s, u, o, c) {
  return `Cannot include a '${s}' character in a manually specified \`to.${u}\` field [${JSON.stringify(
    c
  )}].  Please separate it out to the \`to.${o}\` field. Alternatively you may provide the full path as a string in <Link to="..."> and the router will parse it for you.`;
}
function qx(s) {
  return s.filter(
    (u, o) => o === 0 || (u.route.path && u.route.path.length > 0)
  );
}
function lh(s) {
  let u = qx(s);
  return u.map((o, c) => (c === u.length - 1 ? o.pathname : o.pathnameBase));
}
function nh(s, u, o, c = !1) {
  let f;
  typeof s == "string"
    ? (f = Hl(s))
    : ((f = { ...s }),
      ze(
        !f.pathname || !f.pathname.includes("?"),
        sc("?", "pathname", "search", f)
      ),
      ze(
        !f.pathname || !f.pathname.includes("#"),
        sc("#", "pathname", "hash", f)
      ),
      ze(!f.search || !f.search.includes("#"), sc("#", "search", "hash", f)));
  let m = s === "" || f.pathname === "",
    x = m ? "/" : f.pathname,
    b;
  if (x == null) b = o;
  else {
    let R = u.length - 1;
    if (!c && x.startsWith("..")) {
      let N = x.split("/");
      for (; N[0] === ".."; ) N.shift(), (R -= 1);
      f.pathname = N.join("/");
    }
    b = R >= 0 ? u[R] : "/";
  }
  let y = Bx(f, b),
    p = x && x !== "/" && x.endsWith("/"),
    v = (m || x === ".") && o.endsWith("/");
  return !y.pathname.endsWith("/") && (p || v) && (y.pathname += "/"), y;
}
var la = (s) => s.join("/").replace(/\/\/+/g, "/"),
  Yx = (s) => s.replace(/\/+$/, "").replace(/^\/*/, "/"),
  Vx = (s) => (!s || s === "?" ? "" : s.startsWith("?") ? s : "?" + s),
  Gx = (s) => (!s || s === "#" ? "" : s.startsWith("#") ? s : "#" + s);
function Xx(s) {
  return (
    s != null &&
    typeof s.status == "number" &&
    typeof s.statusText == "string" &&
    typeof s.internal == "boolean" &&
    "data" in s
  );
}
var sh = ["POST", "PUT", "PATCH", "DELETE"];
new Set(sh);
var Qx = ["GET", ...sh];
new Set(Qx);
var ql = T.createContext(null);
ql.displayName = "DataRouter";
var Ri = T.createContext(null);
Ri.displayName = "DataRouterState";
var ih = T.createContext({ isTransitioning: !1 });
ih.displayName = "ViewTransition";
var Zx = T.createContext(new Map());
Zx.displayName = "Fetchers";
var Kx = T.createContext(null);
Kx.displayName = "Await";
var Yt = T.createContext(null);
Yt.displayName = "Navigation";
var $n = T.createContext(null);
$n.displayName = "Location";
var sa = T.createContext({ outlet: null, matches: [], isDataRoute: !1 });
sa.displayName = "Route";
var vc = T.createContext(null);
vc.displayName = "RouteError";
function Jx(s, { relative: u } = {}) {
  ze(
    Fn(),
    "useHref() may be used only in the context of a <Router> component."
  );
  let { basename: o, navigator: c } = T.useContext(Yt),
    { hash: f, pathname: m, search: x } = Wn(s, { relative: u }),
    b = m;
  return (
    o !== "/" && (b = m === "/" ? o : la([o, m])),
    c.createHref({ pathname: b, search: x, hash: f })
  );
}
function Fn() {
  return T.useContext($n) != null;
}
function Ia() {
  return (
    ze(
      Fn(),
      "useLocation() may be used only in the context of a <Router> component."
    ),
    T.useContext($n).location
  );
}
var rh =
  "You should call navigate() in a React.useEffect(), not when your component is first rendered.";
function uh(s) {
  T.useContext(Yt).static || T.useLayoutEffect(s);
}
function $x() {
  let { isDataRoute: s } = T.useContext(sa);
  return s ? ug() : Fx();
}
function Fx() {
  ze(
    Fn(),
    "useNavigate() may be used only in the context of a <Router> component."
  );
  let s = T.useContext(ql),
    { basename: u, navigator: o } = T.useContext(Yt),
    { matches: c } = T.useContext(sa),
    { pathname: f } = Ia(),
    m = JSON.stringify(lh(c)),
    x = T.useRef(!1);
  return (
    uh(() => {
      x.current = !0;
    }),
    T.useCallback(
      (y, p = {}) => {
        if ((qt(x.current, rh), !x.current)) return;
        if (typeof y == "number") {
          o.go(y);
          return;
        }
        let v = nh(y, JSON.parse(m), f, p.relative === "path");
        s == null &&
          u !== "/" &&
          (v.pathname = v.pathname === "/" ? u : la([u, v.pathname])),
          (p.replace ? o.replace : o.push)(v, p.state, p);
      },
      [u, o, m, f, s]
    )
  );
}
T.createContext(null);
function Wn(s, { relative: u } = {}) {
  let { matches: o } = T.useContext(sa),
    { pathname: c } = Ia(),
    f = JSON.stringify(lh(o));
  return T.useMemo(() => nh(s, JSON.parse(f), c, u === "path"), [s, f, c, u]);
}
function Wx(s, u) {
  return ch(s, u);
}
function ch(s, u, o, c) {
  var Y;
  ze(
    Fn(),
    "useRoutes() may be used only in the context of a <Router> component."
  );
  let { navigator: f } = T.useContext(Yt),
    { matches: m } = T.useContext(sa),
    x = m[m.length - 1],
    b = x ? x.params : {},
    y = x ? x.pathname : "/",
    p = x ? x.pathnameBase : "/",
    v = x && x.route;
  {
    let Z = (v && v.path) || "";
    oh(
      y,
      !v || Z.endsWith("*") || Z.endsWith("*?"),
      `You rendered descendant <Routes> (or called \`useRoutes()\`) at "${y}" (under <Route path="${Z}">) but the parent route path has no trailing "*". This means if you navigate deeper, the parent won't match anymore and therefore the child routes will never render.

Please change the parent <Route path="${Z}"> to <Route path="${
        Z === "/" ? "*" : `${Z}/*`
      }">.`
    );
  }
  let R = Ia(),
    N;
  if (u) {
    let Z = typeof u == "string" ? Hl(u) : u;
    ze(
      p === "/" || ((Y = Z.pathname) == null ? void 0 : Y.startsWith(p)),
      `When overriding the location using \`<Routes location>\` or \`useRoutes(routes, location)\`, the location pathname must begin with the portion of the URL pathname that was matched by all parent routes. The current pathname base is "${p}" but pathname "${Z.pathname}" was given in the \`location\` prop.`
    ),
      (N = Z);
  } else N = R;
  let L = N.pathname || "/",
    w = L;
  if (p !== "/") {
    let Z = p.replace(/^\//, "").split("/");
    w = "/" + L.replace(/^\//, "").split("/").slice(Z.length).join("/");
  }
  let C = eh(s, { pathname: w });
  qt(
    v || C != null,
    `No routes matched location "${N.pathname}${N.search}${N.hash}" `
  ),
    qt(
      C == null ||
        C[C.length - 1].route.element !== void 0 ||
        C[C.length - 1].route.Component !== void 0 ||
        C[C.length - 1].route.lazy !== void 0,
      `Matched leaf route at location "${N.pathname}${N.search}${N.hash}" does not have an element or Component. This means it will render an <Outlet /> with a null value by default resulting in an "empty" page.`
    );
  let D = ag(
    C &&
      C.map((Z) =>
        Object.assign({}, Z, {
          params: Object.assign({}, b, Z.params),
          pathname: la([
            p,
            f.encodeLocation
              ? f.encodeLocation(Z.pathname).pathname
              : Z.pathname,
          ]),
          pathnameBase:
            Z.pathnameBase === "/"
              ? p
              : la([
                  p,
                  f.encodeLocation
                    ? f.encodeLocation(Z.pathnameBase).pathname
                    : Z.pathnameBase,
                ]),
        })
      ),
    m,
    o,
    c
  );
  return u && D
    ? T.createElement(
        $n.Provider,
        {
          value: {
            location: {
              pathname: "/",
              search: "",
              hash: "",
              state: null,
              key: "default",
              ...N,
            },
            navigationType: "POP",
          },
        },
        D
      )
    : D;
}
function Px() {
  let s = rg(),
    u = Xx(s)
      ? `${s.status} ${s.statusText}`
      : s instanceof Error
      ? s.message
      : JSON.stringify(s),
    o = s instanceof Error ? s.stack : null,
    c = "rgba(200,200,200, 0.5)",
    f = { padding: "0.5rem", backgroundColor: c },
    m = { padding: "2px 4px", backgroundColor: c },
    x = null;
  return (
    console.error("Error handled by React Router default ErrorBoundary:", s),
    (x = T.createElement(
      T.Fragment,
      null,
      T.createElement("p", null, "💿 Hey developer 👋"),
      T.createElement(
        "p",
        null,
        "You can provide a way better UX than this when your app throws errors by providing your own ",
        T.createElement("code", { style: m }, "ErrorBoundary"),
        " or",
        " ",
        T.createElement("code", { style: m }, "errorElement"),
        " prop on your route."
      )
    )),
    T.createElement(
      T.Fragment,
      null,
      T.createElement("h2", null, "Unexpected Application Error!"),
      T.createElement("h3", { style: { fontStyle: "italic" } }, u),
      o ? T.createElement("pre", { style: f }, o) : null,
      x
    )
  );
}
var Ix = T.createElement(Px, null),
  eg = class extends T.Component {
    constructor(s) {
      super(s),
        (this.state = {
          location: s.location,
          revalidation: s.revalidation,
          error: s.error,
        });
    }
    static getDerivedStateFromError(s) {
      return { error: s };
    }
    static getDerivedStateFromProps(s, u) {
      return u.location !== s.location ||
        (u.revalidation !== "idle" && s.revalidation === "idle")
        ? { error: s.error, location: s.location, revalidation: s.revalidation }
        : {
            error: s.error !== void 0 ? s.error : u.error,
            location: u.location,
            revalidation: s.revalidation || u.revalidation,
          };
    }
    componentDidCatch(s, u) {
      console.error(
        "React Router caught the following error during render",
        s,
        u
      );
    }
    render() {
      return this.state.error !== void 0
        ? T.createElement(
            sa.Provider,
            { value: this.props.routeContext },
            T.createElement(vc.Provider, {
              value: this.state.error,
              children: this.props.component,
            })
          )
        : this.props.children;
    }
  };
function tg({ routeContext: s, match: u, children: o }) {
  let c = T.useContext(ql);
  return (
    c &&
      c.static &&
      c.staticContext &&
      (u.route.errorElement || u.route.ErrorBoundary) &&
      (c.staticContext._deepestRenderedBoundaryId = u.route.id),
    T.createElement(sa.Provider, { value: s }, o)
  );
}
function ag(s, u = [], o = null, c = null) {
  if (s == null) {
    if (!o) return null;
    if (o.errors) s = o.matches;
    else if (u.length === 0 && !o.initialized && o.matches.length > 0)
      s = o.matches;
    else return null;
  }
  let f = s,
    m = o == null ? void 0 : o.errors;
  if (m != null) {
    let y = f.findIndex(
      (p) => p.route.id && (m == null ? void 0 : m[p.route.id]) !== void 0
    );
    ze(
      y >= 0,
      `Could not find a matching route for errors on route IDs: ${Object.keys(
        m
      ).join(",")}`
    ),
      (f = f.slice(0, Math.min(f.length, y + 1)));
  }
  let x = !1,
    b = -1;
  if (o)
    for (let y = 0; y < f.length; y++) {
      let p = f[y];
      if (
        ((p.route.HydrateFallback || p.route.hydrateFallbackElement) && (b = y),
        p.route.id)
      ) {
        let { loaderData: v, errors: R } = o,
          N =
            p.route.loader &&
            !v.hasOwnProperty(p.route.id) &&
            (!R || R[p.route.id] === void 0);
        if (p.route.lazy || N) {
          (x = !0), b >= 0 ? (f = f.slice(0, b + 1)) : (f = [f[0]]);
          break;
        }
      }
    }
  return f.reduceRight((y, p, v) => {
    let R,
      N = !1,
      L = null,
      w = null;
    o &&
      ((R = m && p.route.id ? m[p.route.id] : void 0),
      (L = p.route.errorElement || Ix),
      x &&
        (b < 0 && v === 0
          ? (oh(
              "route-fallback",
              !1,
              "No `HydrateFallback` element provided to render during initial hydration"
            ),
            (N = !0),
            (w = null))
          : b === v &&
            ((N = !0), (w = p.route.hydrateFallbackElement || null))));
    let C = u.concat(f.slice(0, v + 1)),
      D = () => {
        let Y;
        return (
          R
            ? (Y = L)
            : N
            ? (Y = w)
            : p.route.Component
            ? (Y = T.createElement(p.route.Component, null))
            : p.route.element
            ? (Y = p.route.element)
            : (Y = y),
          T.createElement(tg, {
            match: p,
            routeContext: { outlet: y, matches: C, isDataRoute: o != null },
            children: Y,
          })
        );
      };
    return o && (p.route.ErrorBoundary || p.route.errorElement || v === 0)
      ? T.createElement(eg, {
          location: o.location,
          revalidation: o.revalidation,
          component: L,
          error: R,
          children: D(),
          routeContext: { outlet: null, matches: C, isDataRoute: !0 },
        })
      : D();
  }, null);
}
function jc(s) {
  return `${s} must be used within a data router.  See https://reactrouter.com/en/main/routers/picking-a-router.`;
}
function lg(s) {
  let u = T.useContext(ql);
  return ze(u, jc(s)), u;
}
function ng(s) {
  let u = T.useContext(Ri);
  return ze(u, jc(s)), u;
}
function sg(s) {
  let u = T.useContext(sa);
  return ze(u, jc(s)), u;
}
function Sc(s) {
  let u = sg(s),
    o = u.matches[u.matches.length - 1];
  return (
    ze(
      o.route.id,
      `${s} can only be used on routes that contain a unique "id"`
    ),
    o.route.id
  );
}
function ig() {
  return Sc("useRouteId");
}
function rg() {
  var c;
  let s = T.useContext(vc),
    u = ng("useRouteError"),
    o = Sc("useRouteError");
  return s !== void 0 ? s : (c = u.errors) == null ? void 0 : c[o];
}
function ug() {
  let { router: s } = lg("useNavigate"),
    u = Sc("useNavigate"),
    o = T.useRef(!1);
  return (
    uh(() => {
      o.current = !0;
    }),
    T.useCallback(
      async (f, m = {}) => {
        qt(o.current, rh),
          o.current &&
            (typeof f == "number"
              ? s.navigate(f)
              : await s.navigate(f, { fromRouteId: u, ...m }));
      },
      [s, u]
    )
  );
}
var km = {};
function oh(s, u, o) {
  !u && !km[s] && ((km[s] = !0), qt(!1, o));
}
T.memo(cg);
function cg({ routes: s, future: u, state: o }) {
  return ch(s, void 0, o, u);
}
function Oa(s) {
  ze(
    !1,
    "A <Route> is only ever to be used as the child of <Routes> element, never rendered directly. Please wrap your <Route> in a <Routes>."
  );
}
function og({
  basename: s = "/",
  children: u = null,
  location: o,
  navigationType: c = "POP",
  navigator: f,
  static: m = !1,
}) {
  ze(
    !Fn(),
    "You cannot render a <Router> inside another <Router>. You should never have more than one in your app."
  );
  let x = s.replace(/^\/*/, "/"),
    b = T.useMemo(
      () => ({ basename: x, navigator: f, static: m, future: {} }),
      [x, f, m]
    );
  typeof o == "string" && (o = Hl(o));
  let {
      pathname: y = "/",
      search: p = "",
      hash: v = "",
      state: R = null,
      key: N = "default",
    } = o,
    L = T.useMemo(() => {
      let w = na(y, x);
      return w == null
        ? null
        : {
            location: { pathname: w, search: p, hash: v, state: R, key: N },
            navigationType: c,
          };
    }, [x, y, p, v, R, N, c]);
  return (
    qt(
      L != null,
      `<Router basename="${x}"> is not able to match the URL "${y}${p}${v}" because it does not start with the basename, so the <Router> won't render anything.`
    ),
    L == null
      ? null
      : T.createElement(
          Yt.Provider,
          { value: b },
          T.createElement($n.Provider, { children: u, value: L })
        )
  );
}
function fg({ children: s, location: u }) {
  return Wx(dc(s), u);
}
function dc(s, u = []) {
  let o = [];
  return (
    T.Children.forEach(s, (c, f) => {
      if (!T.isValidElement(c)) return;
      let m = [...u, f];
      if (c.type === T.Fragment) {
        o.push.apply(o, dc(c.props.children, m));
        return;
      }
      ze(
        c.type === Oa,
        `[${
          typeof c.type == "string" ? c.type : c.type.name
        }] is not a <Route> component. All component children of <Routes> must be a <Route> or <React.Fragment>`
      ),
        ze(
          !c.props.index || !c.props.children,
          "An index route cannot have child routes."
        );
      let x = {
        id: c.props.id || m.join("-"),
        caseSensitive: c.props.caseSensitive,
        element: c.props.element,
        Component: c.props.Component,
        index: c.props.index,
        path: c.props.path,
        loader: c.props.loader,
        action: c.props.action,
        hydrateFallbackElement: c.props.hydrateFallbackElement,
        HydrateFallback: c.props.HydrateFallback,
        errorElement: c.props.errorElement,
        ErrorBoundary: c.props.ErrorBoundary,
        hasErrorBoundary:
          c.props.hasErrorBoundary === !0 ||
          c.props.ErrorBoundary != null ||
          c.props.errorElement != null,
        shouldRevalidate: c.props.shouldRevalidate,
        handle: c.props.handle,
        lazy: c.props.lazy,
      };
      c.props.children && (x.children = dc(c.props.children, m)), o.push(x);
    }),
    o
  );
}
var ji = "get",
  Si = "application/x-www-form-urlencoded";
function Ci(s) {
  return s != null && typeof s.tagName == "string";
}
function dg(s) {
  return Ci(s) && s.tagName.toLowerCase() === "button";
}
function mg(s) {
  return Ci(s) && s.tagName.toLowerCase() === "form";
}
function hg(s) {
  return Ci(s) && s.tagName.toLowerCase() === "input";
}
function pg(s) {
  return !!(s.metaKey || s.altKey || s.ctrlKey || s.shiftKey);
}
function xg(s, u) {
  return s.button === 0 && (!u || u === "_self") && !pg(s);
}
var pi = null;
function gg() {
  if (pi === null)
    try {
      new FormData(document.createElement("form"), 0), (pi = !1);
    } catch {
      pi = !0;
    }
  return pi;
}
var yg = new Set([
  "application/x-www-form-urlencoded",
  "multipart/form-data",
  "text/plain",
]);
function ic(s) {
  return s != null && !yg.has(s)
    ? (qt(
        !1,
        `"${s}" is not a valid \`encType\` for \`<Form>\`/\`<fetcher.Form>\` and will default to "${Si}"`
      ),
      null)
    : s;
}
function bg(s, u) {
  let o, c, f, m, x;
  if (mg(s)) {
    let b = s.getAttribute("action");
    (c = b ? na(b, u) : null),
      (o = s.getAttribute("method") || ji),
      (f = ic(s.getAttribute("enctype")) || Si),
      (m = new FormData(s));
  } else if (dg(s) || (hg(s) && (s.type === "submit" || s.type === "image"))) {
    let b = s.form;
    if (b == null)
      throw new Error(
        'Cannot submit a <button> or <input type="submit"> without a <form>'
      );
    let y = s.getAttribute("formaction") || b.getAttribute("action");
    if (
      ((c = y ? na(y, u) : null),
      (o = s.getAttribute("formmethod") || b.getAttribute("method") || ji),
      (f =
        ic(s.getAttribute("formenctype")) ||
        ic(b.getAttribute("enctype")) ||
        Si),
      (m = new FormData(b, s)),
      !gg())
    ) {
      let { name: p, type: v, value: R } = s;
      if (v === "image") {
        let N = p ? `${p}.` : "";
        m.append(`${N}x`, "0"), m.append(`${N}y`, "0");
      } else p && m.append(p, R);
    }
  } else {
    if (Ci(s))
      throw new Error(
        'Cannot submit element that is not <form>, <button>, or <input type="submit|image">'
      );
    (o = ji), (c = null), (f = Si), (x = s);
  }
  return (
    m && f === "text/plain" && ((x = m), (m = void 0)),
    { action: c, method: o.toLowerCase(), encType: f, formData: m, body: x }
  );
}
function Nc(s, u) {
  if (s === !1 || s === null || typeof s > "u") throw new Error(u);
}
async function vg(s, u) {
  if (s.id in u) return u[s.id];
  try {
    let o = await import(s.module);
    return (u[s.id] = o), o;
  } catch (o) {
    return (
      console.error(
        `Error loading route module \`${s.module}\`, reloading page...`
      ),
      console.error(o),
      window.__reactRouterContext && window.__reactRouterContext.isSpaMode,
      window.location.reload(),
      new Promise(() => {})
    );
  }
}
function jg(s) {
  return s == null
    ? !1
    : s.href == null
    ? s.rel === "preload" &&
      typeof s.imageSrcSet == "string" &&
      typeof s.imageSizes == "string"
    : typeof s.rel == "string" && typeof s.href == "string";
}
async function Sg(s, u, o) {
  let c = await Promise.all(
    s.map(async (f) => {
      let m = u.routes[f.route.id];
      if (m) {
        let x = await vg(m, o);
        return x.links ? x.links() : [];
      }
      return [];
    })
  );
  return Tg(
    c
      .flat(1)
      .filter(jg)
      .filter((f) => f.rel === "stylesheet" || f.rel === "preload")
      .map((f) =>
        f.rel === "stylesheet"
          ? { ...f, rel: "prefetch", as: "style" }
          : { ...f, rel: "prefetch" }
      )
  );
}
function Lm(s, u, o, c, f, m) {
  let x = (y, p) => (o[p] ? y.route.id !== o[p].route.id : !0),
    b = (y, p) => {
      var v;
      return (
        o[p].pathname !== y.pathname ||
        (((v = o[p].route.path) == null ? void 0 : v.endsWith("*")) &&
          o[p].params["*"] !== y.params["*"])
      );
    };
  return m === "assets"
    ? u.filter((y, p) => x(y, p) || b(y, p))
    : m === "data"
    ? u.filter((y, p) => {
        var R;
        let v = c.routes[y.route.id];
        if (!v || !v.hasLoader) return !1;
        if (x(y, p) || b(y, p)) return !0;
        if (y.route.shouldRevalidate) {
          let N = y.route.shouldRevalidate({
            currentUrl: new URL(f.pathname + f.search + f.hash, window.origin),
            currentParams: ((R = o[0]) == null ? void 0 : R.params) || {},
            nextUrl: new URL(s, window.origin),
            nextParams: y.params,
            defaultShouldRevalidate: !0,
          });
          if (typeof N == "boolean") return N;
        }
        return !0;
      })
    : [];
}
function Ng(s, u, { includeHydrateFallback: o } = {}) {
  return wg(
    s
      .map((c) => {
        let f = u.routes[c.route.id];
        if (!f) return [];
        let m = [f.module];
        return (
          f.clientActionModule && (m = m.concat(f.clientActionModule)),
          f.clientLoaderModule && (m = m.concat(f.clientLoaderModule)),
          o &&
            f.hydrateFallbackModule &&
            (m = m.concat(f.hydrateFallbackModule)),
          f.imports && (m = m.concat(f.imports)),
          m
        );
      })
      .flat(1)
  );
}
function wg(s) {
  return [...new Set(s)];
}
function Eg(s) {
  let u = {},
    o = Object.keys(s).sort();
  for (let c of o) u[c] = s[c];
  return u;
}
function Tg(s, u) {
  let o = new Set();
  return (
    new Set(u),
    s.reduce((c, f) => {
      let m = JSON.stringify(Eg(f));
      return o.has(m) || (o.add(m), c.push({ key: m, link: f })), c;
    }, [])
  );
}
Object.getOwnPropertyNames(Object.prototype).sort().join("\0");
var _g = new Set([100, 101, 204, 205]);
function Ag(s, u) {
  let o =
    typeof s == "string"
      ? new URL(
          s,
          typeof window > "u" ? "server://singlefetch/" : window.location.origin
        )
      : s;
  return (
    o.pathname === "/"
      ? (o.pathname = "_root.data")
      : u && na(o.pathname, u) === "/"
      ? (o.pathname = `${u.replace(/\/$/, "")}/_root.data`)
      : (o.pathname = `${o.pathname.replace(/\/$/, "")}.data`),
    o
  );
}
function fh() {
  let s = T.useContext(ql);
  return (
    Nc(
      s,
      "You must render this element inside a <DataRouterContext.Provider> element"
    ),
    s
  );
}
function Rg() {
  let s = T.useContext(Ri);
  return (
    Nc(
      s,
      "You must render this element inside a <DataRouterStateContext.Provider> element"
    ),
    s
  );
}
var wc = T.createContext(void 0);
wc.displayName = "FrameworkContext";
function dh() {
  let s = T.useContext(wc);
  return (
    Nc(s, "You must render this element inside a <HydratedRouter> element"), s
  );
}
function Cg(s, u) {
  let o = T.useContext(wc),
    [c, f] = T.useState(!1),
    [m, x] = T.useState(!1),
    {
      onFocus: b,
      onBlur: y,
      onMouseEnter: p,
      onMouseLeave: v,
      onTouchStart: R,
    } = u,
    N = T.useRef(null);
  T.useEffect(() => {
    if ((s === "render" && x(!0), s === "viewport")) {
      let C = (Y) => {
          Y.forEach((Z) => {
            x(Z.isIntersecting);
          });
        },
        D = new IntersectionObserver(C, { threshold: 0.5 });
      return (
        N.current && D.observe(N.current),
        () => {
          D.disconnect();
        }
      );
    }
  }, [s]),
    T.useEffect(() => {
      if (c) {
        let C = setTimeout(() => {
          x(!0);
        }, 100);
        return () => {
          clearTimeout(C);
        };
      }
    }, [c]);
  let L = () => {
      f(!0);
    },
    w = () => {
      f(!1), x(!1);
    };
  return o
    ? s !== "intent"
      ? [m, N, {}]
      : [
          m,
          N,
          {
            onFocus: Xn(b, L),
            onBlur: Xn(y, w),
            onMouseEnter: Xn(p, L),
            onMouseLeave: Xn(v, w),
            onTouchStart: Xn(R, L),
          },
        ]
    : [!1, N, {}];
}
function Xn(s, u) {
  return (o) => {
    s && s(o), o.defaultPrevented || u(o);
  };
}
function Og({ page: s, ...u }) {
  let { router: o } = fh(),
    c = T.useMemo(() => eh(o.routes, s, o.basename), [o.routes, s, o.basename]);
  return c ? T.createElement(Dg, { page: s, matches: c, ...u }) : null;
}
function Mg(s) {
  let { manifest: u, routeModules: o } = dh(),
    [c, f] = T.useState([]);
  return (
    T.useEffect(() => {
      let m = !1;
      return (
        Sg(s, u, o).then((x) => {
          m || f(x);
        }),
        () => {
          m = !0;
        }
      );
    }, [s, u, o]),
    c
  );
}
function Dg({ page: s, matches: u, ...o }) {
  let c = Ia(),
    { manifest: f, routeModules: m } = dh(),
    { basename: x } = fh(),
    { loaderData: b, matches: y } = Rg(),
    p = T.useMemo(() => Lm(s, u, y, f, c, "data"), [s, u, y, f, c]),
    v = T.useMemo(() => Lm(s, u, y, f, c, "assets"), [s, u, y, f, c]),
    R = T.useMemo(() => {
      if (s === c.pathname + c.search + c.hash) return [];
      let w = new Set(),
        C = !1;
      if (
        (u.forEach((Y) => {
          var U;
          let Z = f.routes[Y.route.id];
          !Z ||
            !Z.hasLoader ||
            ((!p.some((q) => q.route.id === Y.route.id) &&
              Y.route.id in b &&
              (U = m[Y.route.id]) != null &&
              U.shouldRevalidate) ||
            Z.hasClientLoader
              ? (C = !0)
              : w.add(Y.route.id));
        }),
        w.size === 0)
      )
        return [];
      let D = Ag(s, x);
      return (
        C &&
          w.size > 0 &&
          D.searchParams.set(
            "_routes",
            u
              .filter((Y) => w.has(Y.route.id))
              .map((Y) => Y.route.id)
              .join(",")
          ),
        [D.pathname + D.search]
      );
    }, [x, b, c, f, p, u, s, m]),
    N = T.useMemo(() => Ng(v, f), [v, f]),
    L = Mg(v);
  return T.createElement(
    T.Fragment,
    null,
    R.map((w) =>
      T.createElement("link", {
        key: w,
        rel: "prefetch",
        as: "fetch",
        href: w,
        ...o,
      })
    ),
    N.map((w) =>
      T.createElement("link", { key: w, rel: "modulepreload", href: w, ...o })
    ),
    L.map(({ key: w, link: C }) => T.createElement("link", { key: w, ...C }))
  );
}
function zg(...s) {
  return (u) => {
    s.forEach((o) => {
      typeof o == "function" ? o(u) : o != null && (o.current = u);
    });
  };
}
var mh =
  typeof window < "u" &&
  typeof window.document < "u" &&
  typeof window.document.createElement < "u";
try {
  mh && (window.__reactRouterVersion = "7.6.2");
} catch {}
function Ug({ basename: s, children: u, window: o }) {
  let c = T.useRef();
  c.current == null && (c.current = jx({ window: o, v5Compat: !0 }));
  let f = c.current,
    [m, x] = T.useState({ action: f.action, location: f.location }),
    b = T.useCallback(
      (y) => {
        T.startTransition(() => x(y));
      },
      [x]
    );
  return (
    T.useLayoutEffect(() => f.listen(b), [f, b]),
    T.createElement(og, {
      basename: s,
      children: u,
      location: m.location,
      navigationType: m.action,
      navigator: f,
    })
  );
}
var hh = /^(?:[a-z][a-z0-9+.-]*:|\/\/)/i,
  ph = T.forwardRef(function (
    {
      onClick: u,
      discover: o = "render",
      prefetch: c = "none",
      relative: f,
      reloadDocument: m,
      replace: x,
      state: b,
      target: y,
      to: p,
      preventScrollReset: v,
      viewTransition: R,
      ...N
    },
    L
  ) {
    let { basename: w } = T.useContext(Yt),
      C = typeof p == "string" && hh.test(p),
      D,
      Y = !1;
    if (typeof p == "string" && C && ((D = p), mh))
      try {
        let ce = new URL(window.location.href),
          je = p.startsWith("//") ? new URL(ce.protocol + p) : new URL(p),
          Ue = na(je.pathname, w);
        je.origin === ce.origin && Ue != null
          ? (p = Ue + je.search + je.hash)
          : (Y = !0);
      } catch {
        qt(
          !1,
          `<Link to="${p}"> contains an invalid URL which will probably break when clicked - please update to a valid URL path.`
        );
      }
    let Z = Jx(p, { relative: f }),
      [U, q, Q] = Cg(c, N),
      W = Bg(p, {
        replace: x,
        state: b,
        target: y,
        preventScrollReset: v,
        relative: f,
        viewTransition: R,
      });
    function re(ce) {
      u && u(ce), ce.defaultPrevented || W(ce);
    }
    let ue = T.createElement("a", {
      ...N,
      ...Q,
      href: D || Z,
      onClick: Y || m ? u : re,
      ref: zg(L, q),
      target: y,
      "data-discover": !C && o === "render" ? "true" : void 0,
    });
    return U && !C
      ? T.createElement(T.Fragment, null, ue, T.createElement(Og, { page: Z }))
      : ue;
  });
ph.displayName = "Link";
var Zn = T.forwardRef(function (
  {
    "aria-current": u = "page",
    caseSensitive: o = !1,
    className: c = "",
    end: f = !1,
    style: m,
    to: x,
    viewTransition: b,
    children: y,
    ...p
  },
  v
) {
  let R = Wn(x, { relative: p.relative }),
    N = Ia(),
    L = T.useContext(Ri),
    { navigator: w, basename: C } = T.useContext(Yt),
    D = L != null && Gg(R) && b === !0,
    Y = w.encodeLocation ? w.encodeLocation(R).pathname : R.pathname,
    Z = N.pathname,
    U =
      L && L.navigation && L.navigation.location
        ? L.navigation.location.pathname
        : null;
  o ||
    ((Z = Z.toLowerCase()),
    (U = U ? U.toLowerCase() : null),
    (Y = Y.toLowerCase())),
    U && C && (U = na(U, C) || U);
  const q = Y !== "/" && Y.endsWith("/") ? Y.length - 1 : Y.length;
  let Q = Z === Y || (!f && Z.startsWith(Y) && Z.charAt(q) === "/"),
    W =
      U != null &&
      (U === Y || (!f && U.startsWith(Y) && U.charAt(Y.length) === "/")),
    re = { isActive: Q, isPending: W, isTransitioning: D },
    ue = Q ? u : void 0,
    ce;
  typeof c == "function"
    ? (ce = c(re))
    : (ce = [
        c,
        Q ? "active" : null,
        W ? "pending" : null,
        D ? "transitioning" : null,
      ]
        .filter(Boolean)
        .join(" "));
  let je = typeof m == "function" ? m(re) : m;
  return T.createElement(
    ph,
    {
      ...p,
      "aria-current": ue,
      className: ce,
      ref: v,
      style: je,
      to: x,
      viewTransition: b,
    },
    typeof y == "function" ? y(re) : y
  );
});
Zn.displayName = "NavLink";
var kg = T.forwardRef(
  (
    {
      discover: s = "render",
      fetcherKey: u,
      navigate: o,
      reloadDocument: c,
      replace: f,
      state: m,
      method: x = ji,
      action: b,
      onSubmit: y,
      relative: p,
      preventScrollReset: v,
      viewTransition: R,
      ...N
    },
    L
  ) => {
    let w = Yg(),
      C = Vg(b, { relative: p }),
      D = x.toLowerCase() === "get" ? "get" : "post",
      Y = typeof b == "string" && hh.test(b),
      Z = (U) => {
        if ((y && y(U), U.defaultPrevented)) return;
        U.preventDefault();
        let q = U.nativeEvent.submitter,
          Q = (q == null ? void 0 : q.getAttribute("formmethod")) || x;
        w(q || U.currentTarget, {
          fetcherKey: u,
          method: Q,
          navigate: o,
          replace: f,
          state: m,
          relative: p,
          preventScrollReset: v,
          viewTransition: R,
        });
      };
    return T.createElement("form", {
      ref: L,
      method: D,
      action: C,
      onSubmit: c ? y : Z,
      ...N,
      "data-discover": !Y && s === "render" ? "true" : void 0,
    });
  }
);
kg.displayName = "Form";
function Lg(s) {
  return `${s} must be used within a data router.  See https://reactrouter.com/en/main/routers/picking-a-router.`;
}
function xh(s) {
  let u = T.useContext(ql);
  return ze(u, Lg(s)), u;
}
function Bg(
  s,
  {
    target: u,
    replace: o,
    state: c,
    preventScrollReset: f,
    relative: m,
    viewTransition: x,
  } = {}
) {
  let b = $x(),
    y = Ia(),
    p = Wn(s, { relative: m });
  return T.useCallback(
    (v) => {
      if (xg(v, u)) {
        v.preventDefault();
        let R = o !== void 0 ? o : Kn(y) === Kn(p);
        b(s, {
          replace: R,
          state: c,
          preventScrollReset: f,
          relative: m,
          viewTransition: x,
        });
      }
    },
    [y, b, p, o, c, u, s, f, m, x]
  );
}
var Hg = 0,
  qg = () => `__${String(++Hg)}__`;
function Yg() {
  let { router: s } = xh("useSubmit"),
    { basename: u } = T.useContext(Yt),
    o = ig();
  return T.useCallback(
    async (c, f = {}) => {
      let { action: m, method: x, encType: b, formData: y, body: p } = bg(c, u);
      if (f.navigate === !1) {
        let v = f.fetcherKey || qg();
        await s.fetch(v, o, f.action || m, {
          preventScrollReset: f.preventScrollReset,
          formData: y,
          body: p,
          formMethod: f.method || x,
          formEncType: f.encType || b,
          flushSync: f.flushSync,
        });
      } else
        await s.navigate(f.action || m, {
          preventScrollReset: f.preventScrollReset,
          formData: y,
          body: p,
          formMethod: f.method || x,
          formEncType: f.encType || b,
          replace: f.replace,
          state: f.state,
          fromRouteId: o,
          flushSync: f.flushSync,
          viewTransition: f.viewTransition,
        });
    },
    [s, u, o]
  );
}
function Vg(s, { relative: u } = {}) {
  let { basename: o } = T.useContext(Yt),
    c = T.useContext(sa);
  ze(c, "useFormAction must be used inside a RouteContext");
  let [f] = c.matches.slice(-1),
    m = { ...Wn(s || ".", { relative: u }) },
    x = Ia();
  if (s == null) {
    m.search = x.search;
    let b = new URLSearchParams(m.search),
      y = b.getAll("index");
    if (y.some((v) => v === "")) {
      b.delete("index"),
        y.filter((R) => R).forEach((R) => b.append("index", R));
      let v = b.toString();
      m.search = v ? `?${v}` : "";
    }
  }
  return (
    (!s || s === ".") &&
      f.route.index &&
      (m.search = m.search ? m.search.replace(/^\?/, "?index&") : "?index"),
    o !== "/" && (m.pathname = m.pathname === "/" ? o : la([o, m.pathname])),
    Kn(m)
  );
}
function Gg(s, u = {}) {
  let o = T.useContext(ih);
  ze(
    o != null,
    "`useViewTransitionState` must be used within `react-router-dom`'s `RouterProvider`.  Did you accidentally import `RouterProvider` from `react-router`?"
  );
  let { basename: c } = xh("useViewTransitionState"),
    f = Wn(s, { relative: u.relative });
  if (!o.isTransitioning) return !1;
  let m = na(o.currentLocation.pathname, c) || o.currentLocation.pathname,
    x = na(o.nextLocation.pathname, c) || o.nextLocation.pathname;
  return Ti(f.pathname, x) != null || Ti(f.pathname, m) != null;
}
[..._g];
const Xg = ({ listId: s, onClose: u }) => {
    const [o, c] = T.useState([]),
      [f, m] = T.useState(null),
      [x, b] = T.useState(!1),
      [y, p] = T.useState({ page: 1, rowsPerPage: 10, total: 0 }),
      [v, R] = T.useState("all"),
      N = async () => {
        try {
          b(!0);
          const q = await (
            await fetch(
              `/backend/routes/api.php/api/results?csv_list_id=${s}&limit=1000000`
            )
          ).json();
          c(Array.isArray(q.data) ? q.data : []),
            p((Q) => ({
              ...Q,
              total: Array.isArray(q.data) ? q.data.length : 0,
            }));
        } catch {
          c([]), m({ type: "error", message: "Failed to load list emails" });
        } finally {
          b(!1);
        }
      },
      L = (U) =>
        (U.validation_response || "").toLowerCase().includes("timeout") ||
        (U.validation_response || "")
          .toLowerCase()
          .includes("connection refused") ||
        (U.validation_response || "")
          .toLowerCase()
          .includes("failed to connect"),
      w = o.filter((U) =>
        v === "all"
          ? !0
          : v === "valid"
          ? U.domain_status === 1
          : v === "invalid"
          ? U.domain_status === 0 && !L(U)
          : v === "timeout"
          ? L(U)
          : !0
      ),
      C = w.slice((y.page - 1) * y.rowsPerPage, y.page * y.rowsPerPage);
    T.useEffect(() => {
      N();
    }, [s]),
      T.useEffect(() => {
        p((U) => ({ ...U, page: 1, total: w.length }));
      }, [v, y.rowsPerPage]);
    const D = ({ status: U, onClose: q }) =>
      U &&
      r.jsxs("div", {
        className: `
          fixed top-6 left-1/2 transform -translate-x-1/2 z-50
          px-6 py-3 rounded-xl shadow text-base font-semibold
          flex items-center gap-3
          transition-all duration-300
          backdrop-blur-md
          ${
            U.type === "error"
              ? "bg-red-200/60 border border-red-400 text-red-800"
              : "bg-green-200/60 border border-green-400 text-green-800"
          }
        `,
        style: {
          minWidth: 250,
          maxWidth: 400,
          boxShadow: "0 8px 32px 0 rgba(0, 0, 0, 0.23)",
          background:
            U.type === "error"
              ? "rgba(255, 0, 0, 0.29)"
              : "rgba(0, 200, 83, 0.29)",
          borderRadius: "16px",
          backdropFilter: "blur(8px)",
          WebkitBackdropFilter: "blur(8px)",
        },
        role: "alert",
        children: [
          r.jsx("i", {
            className: `fas text-lg ${
              U.type === "error"
                ? "fa-exclamation-circle text-red-500"
                : "fa-check-circle text-green-500"
            }`,
          }),
          r.jsx("span", { className: "flex-1", children: U.message }),
          r.jsx("button", {
            onClick: q,
            className:
              "ml-2 text-gray-500 hover:text-gray-700 focus:outline-none",
            "aria-label": "Close",
            children: r.jsx("i", { className: "fas fa-times" }),
          }),
        ],
      });
    T.useEffect(() => {
      if (f) {
        const U = setTimeout(() => m(null), 4e3);
        return () => clearTimeout(U);
      }
    }, [f]);
    const Y = (U) => {
        let q = [];
        if (
          (U === "valid"
            ? (q = o.filter((je) => je.domain_status === 1))
            : U === "invalid"
            ? (q = o.filter((je) => je.domain_status === 0 && !L(je)))
            : U === "timeout"
            ? (q = o.filter(L))
            : (q = o),
          q.length === 0)
        ) {
          m({ type: "error", message: "No emails found for export." });
          return;
        }
        const W = [
            "EMAILS",
            ...q.map((je) => `"${je.email.replace(/"/g, '""')}"`),
          ].join(`
`),
          re = new Blob([W], { type: "text/csv" }),
          ue = URL.createObjectURL(re),
          ce = document.createElement("a");
        (ce.href = ue),
          (ce.download = `${U}_emails.csv`),
          document.body.appendChild(ce),
          ce.click(),
          document.body.removeChild(ce),
          URL.revokeObjectURL(ue),
          m({ type: "success", message: "Exported successfully." });
      },
      Z = (U) =>
        U === "valid"
          ? o.filter((q) => q.domain_status === 1).length
          : U === "invalid"
          ? o.filter((q) => q.domain_status === 0 && !L(q)).length
          : U === "timeout"
          ? o.filter(L).length
          : o.length;
    return r.jsx("div", {
      className:
        "fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-md backdrop-saturate-150 p-4",
      children: r.jsxs("div", {
        className:
          "bg-white rounded-xl shadow-lg w-full max-w-6xl max-h-[90vh] flex flex-col",
        onClick: (U) => U.stopPropagation(),
        children: [
          r.jsxs("div", {
            className: "p-6 pb-0 flex justify-between items-start",
            children: [
              r.jsxs("div", {
                children: [
                  r.jsx("h3", {
                    className: "text-2xl font-bold text-gray-800 mb-2",
                    children: "Email List Details",
                  }),
                  r.jsxs("p", {
                    className: "text-gray-600",
                    children: ["List ID: ", s],
                  }),
                ],
              }),
              r.jsx("button", {
                className:
                  "text-gray-500 hover:text-gray-700 p-2 rounded-full hover:bg-gray-100",
                onClick: u,
                "aria-label": "Close",
                children: r.jsx("i", { className: "fas fa-times text-xl" }),
              }),
            ],
          }),
          r.jsx(D, { status: f, onClose: () => m(null) }),
          r.jsx("div", {
            className: "p-6 pt-4 border-b border-gray-200",
            children: r.jsxs("div", {
              className:
                "flex flex-wrap items-center justify-between gap-4 mb-4",
              children: [
                r.jsxs("div", {
                  className: "flex flex-wrap gap-2",
                  children: [
                    r.jsxs("button", {
                      onClick: () => R("all"),
                      className: `px-4 py-2 rounded-lg font-medium text-sm ${
                        v === "all"
                          ? "bg-blue-600 text-white"
                          : "bg-gray-100 text-gray-700 hover:bg-gray-200"
                      }`,
                      children: ["All (", Z("all"), ")"],
                    }),
                    r.jsxs("button", {
                      onClick: () => R("valid"),
                      className: `px-4 py-2 rounded-lg font-medium text-sm ${
                        v === "valid"
                          ? "bg-green-600 text-white"
                          : "bg-gray-100 text-gray-700 hover:bg-gray-200"
                      }`,
                      children: ["Valid (", Z("valid"), ")"],
                    }),
                    r.jsxs("button", {
                      onClick: () => R("invalid"),
                      className: `px-4 py-2 rounded-lg font-medium text-sm ${
                        v === "invalid"
                          ? "bg-red-600 text-white"
                          : "bg-gray-100 text-gray-700 hover:bg-gray-200"
                      }`,
                      children: ["Invalid (", Z("invalid"), ")"],
                    }),
                    r.jsxs("button", {
                      onClick: () => R("timeout"),
                      className: `px-4 py-2 rounded-lg font-medium text-sm ${
                        v === "timeout"
                          ? "bg-yellow-600 text-white"
                          : "bg-gray-100 text-gray-700 hover:bg-gray-200"
                      }`,
                      children: ["Timeout (", Z("timeout"), ")"],
                    }),
                  ],
                }),
                r.jsxs("div", {
                  className: "flex flex-wrap gap-2",
                  children: [
                    r.jsxs("button", {
                      onClick: () => Y("valid"),
                      className:
                        "px-4 py-2 bg-green-600 text-white rounded-lg font-medium text-sm hover:bg-green-700 flex items-center gap-2",
                      children: [
                        r.jsx("i", { className: "fas fa-file-export" }),
                        "Export Valid",
                      ],
                    }),
                    r.jsxs("button", {
                      onClick: () => Y("invalid"),
                      className:
                        "px-4 py-2 bg-red-600 text-white rounded-lg font-medium text-sm hover:bg-red-700 flex items-center gap-2",
                      children: [
                        r.jsx("i", { className: "fas fa-file-export" }),
                        "Export Invalid",
                      ],
                    }),
                    r.jsxs("button", {
                      onClick: () => Y("timeout"),
                      className:
                        "px-4 py-2 bg-yellow-600 text-white rounded-lg font-medium text-sm hover:bg-yellow-700 flex items-center gap-2",
                      children: [
                        r.jsx("i", { className: "fas fa-file-export" }),
                        "Export Timeout",
                      ],
                    }),
                  ],
                }),
              ],
            }),
          }),
          r.jsx("div", {
            className: "overflow-auto flex-1",
            children: r.jsxs("table", {
              className: "min-w-full divide-y divide-gray-200",
              children: [
                r.jsx("thead", {
                  className: "bg-gray-50 sticky top-0",
                  children: r.jsxs("tr", {
                    children: [
                      r.jsx("th", {
                        className:
                          "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "ID",
                      }),
                      r.jsx("th", {
                        className:
                          "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "Email",
                      }),
                      r.jsx("th", {
                        className:
                          "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "Account",
                      }),
                      r.jsx("th", {
                        className:
                          "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "Domain",
                      }),
                      r.jsx("th", {
                        className:
                          "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "Verified",
                      }),
                      r.jsx("th", {
                        className:
                          "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "Status",
                      }),
                      r.jsx("th", {
                        className:
                          "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "Response",
                      }),
                    ],
                  }),
                }),
                r.jsx("tbody", {
                  className: "bg-white divide-y divide-gray-200",
                  children: x
                    ? Array.from({ length: y.rowsPerPage }).map((U, q) =>
                        r.jsx(
                          "tr",
                          {
                            className: "animate-pulse",
                            children: Array.from({ length: 7 }).map((Q, W) =>
                              r.jsx(
                                "td",
                                {
                                  className: "px-6 py-4",
                                  children: r.jsx("div", {
                                    className:
                                      "h-4 bg-gray-200 rounded w-3/4 mx-auto",
                                  }),
                                },
                                W
                              )
                            ),
                          },
                          q
                        )
                      )
                    : C.length === 0
                    ? r.jsx("tr", {
                        children: r.jsx("td", {
                          colSpan: 7,
                          className: "px-6 py-8 text-center text-gray-500",
                          children:
                            "No emails found matching the current filter",
                        }),
                      })
                    : C.map((U) =>
                        r.jsxs(
                          "tr",
                          {
                            className: "hover:bg-gray-50 transition-colors",
                            children: [
                              r.jsx("td", {
                                className:
                                  "px-6 py-4 whitespace-nowrap text-sm text-gray-500",
                                children: U.id,
                              }),
                              r.jsx("td", {
                                className:
                                  "px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900",
                                children: U.raw_emailid || U.email || "N/A",
                              }),
                              r.jsx("td", {
                                className:
                                  "px-6 py-4 whitespace-nowrap text-sm text-gray-500",
                                children: U.sp_account,
                              }),
                              r.jsx("td", {
                                className:
                                  "px-6 py-4 whitespace-nowrap text-sm text-gray-500",
                                children: U.sp_domain,
                              }),
                              r.jsx("td", {
                                className: "px-6 py-4 whitespace-nowrap",
                                children: r.jsx("span", {
                                  className: `px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                    U.domain_verified == 1
                                      ? "bg-green-100 text-green-800"
                                      : "bg-red-100 text-red-800"
                                  }`,
                                  children:
                                    U.domain_verified == 1
                                      ? "Verified"
                                      : "Invalid",
                                }),
                              }),
                              r.jsx("td", {
                                className: "px-6 py-4 whitespace-nowrap",
                                children: r.jsx("span", {
                                  className: `px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                    L(U)
                                      ? "bg-yellow-100 text-yellow-800"
                                      : U.domain_status == 1
                                      ? "bg-blue-100 text-blue-800"
                                      : "bg-orange-100 text-red-800"
                                  }`,
                                  children: L(U)
                                    ? "Timeout"
                                    : U.domain_status == 1
                                    ? "Valid"
                                    : "Invalid",
                                }),
                              }),
                              r.jsx("td", {
                                className:
                                  "px-6 py-4 text-sm text-gray-500 max-w-xs truncate",
                                children: U.validation_response || "N/A",
                              }),
                            ],
                          },
                          U.id
                        )
                      ),
                }),
              ],
            }),
          }),
          r.jsxs("div", {
            className:
              "px-6 py-4 border-t border-gray-200 flex items-center justify-between",
            children: [
              r.jsxs("div", {
                className: "flex-1 flex justify-between sm:hidden",
                children: [
                  r.jsx("button", {
                    onClick: () => p((U) => ({ ...U, page: U.page - 1 })),
                    disabled: y.page === 1,
                    className:
                      "relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50",
                    children: "Previous",
                  }),
                  r.jsx("button", {
                    onClick: () => p((U) => ({ ...U, page: U.page + 1 })),
                    disabled: y.page >= Math.ceil(w.length / y.rowsPerPage),
                    className:
                      "ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50",
                    children: "Next",
                  }),
                ],
              }),
              r.jsxs("div", {
                className:
                  "hidden sm:flex-1 sm:flex sm:items-center sm:justify-between",
                children: [
                  r.jsx("div", {
                    children: r.jsxs("p", {
                      className: "text-sm text-gray-700",
                      children: [
                        "Showing",
                        " ",
                        r.jsx("span", {
                          className: "font-medium",
                          children: (y.page - 1) * y.rowsPerPage + 1,
                        }),
                        " ",
                        "to",
                        " ",
                        r.jsx("span", {
                          className: "font-medium",
                          children: Math.min(y.page * y.rowsPerPage, w.length),
                        }),
                        " ",
                        "of ",
                        r.jsx("span", {
                          className: "font-medium",
                          children: w.length,
                        }),
                        " ",
                        "results",
                      ],
                    }),
                  }),
                  r.jsxs("div", {
                    className: "flex items-center gap-2",
                    children: [
                      r.jsxs("div", {
                        className: "flex items-center",
                        children: [
                          r.jsx("label", {
                            htmlFor: "rows-per-page",
                            className: "mr-2 text-sm text-gray-700",
                            children: "Rows per page:",
                          }),
                          r.jsx("select", {
                            id: "rows-per-page",
                            value: y.rowsPerPage,
                            onChange: (U) => {
                              p((q) => ({
                                ...q,
                                page: 1,
                                rowsPerPage: Number(U.target.value),
                                total: w.length,
                              }));
                            },
                            className:
                              "border border-gray-300 rounded-md shadow-sm py-1 pl-2 pr-8 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500",
                            children: [10, 25, 50, 100, 200].map((U) =>
                              r.jsx("option", { value: U, children: U }, U)
                            ),
                          }),
                        ],
                      }),
                      r.jsxs("nav", {
                        className:
                          "relative z-0 inline-flex rounded-md shadow-sm -space-x-px",
                        "aria-label": "Pagination",
                        children: [
                          r.jsxs("button", {
                            onClick: () => p((U) => ({ ...U, page: 1 })),
                            disabled: y.page === 1,
                            className:
                              "relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50",
                            children: [
                              r.jsx("span", {
                                className: "sr-only",
                                children: "First",
                              }),
                              r.jsx("i", {
                                className: "fas fa-angle-double-left",
                              }),
                            ],
                          }),
                          r.jsxs("button", {
                            onClick: () =>
                              p((U) => ({ ...U, page: U.page - 1 })),
                            disabled: y.page === 1,
                            className:
                              "relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50",
                            children: [
                              r.jsx("span", {
                                className: "sr-only",
                                children: "Previous",
                              }),
                              r.jsx("i", { className: "fas fa-angle-left" }),
                            ],
                          }),
                          r.jsxs("span", {
                            className:
                              "relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700",
                            children: [
                              "Page ",
                              y.page,
                              " of",
                              " ",
                              Math.ceil(w.length / y.rowsPerPage),
                            ],
                          }),
                          r.jsxs("button", {
                            onClick: () =>
                              p((U) => ({ ...U, page: U.page + 1 })),
                            disabled:
                              y.page >= Math.ceil(w.length / y.rowsPerPage),
                            className:
                              "relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50",
                            children: [
                              r.jsx("span", {
                                className: "sr-only",
                                children: "Next",
                              }),
                              r.jsx("i", { className: "fas fa-angle-right" }),
                            ],
                          }),
                          r.jsxs("button", {
                            onClick: () =>
                              p((U) => ({
                                ...U,
                                page: Math.ceil(w.length / y.rowsPerPage),
                              })),
                            disabled:
                              y.page >= Math.ceil(w.length / y.rowsPerPage),
                            className:
                              "relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50",
                            children: [
                              r.jsx("span", {
                                className: "sr-only",
                                children: "Last",
                              }),
                              r.jsx("i", {
                                className: "fas fa-angle-double-right",
                              }),
                            ],
                          }),
                        ],
                      }),
                    ],
                  }),
                ],
              }),
            ],
          }),
        ],
      }),
    });
  },
  Qg = () => {
    const [s, u] = T.useState({ listName: "", fileName: "", csvFile: null }),
      [o, c] = T.useState(null),
      [f, m] = T.useState(!1),
      [x, b] = T.useState({
        processed: 0,
        total: 0,
        percent: 0,
        stage: "domain",
      }),
      [y, p] = T.useState(!1),
      [v, R] = T.useState([]),
      [N, L] = T.useState({ page: 1, rowsPerPage: 10, total: 0, search: "" }),
      [w, C] = T.useState(null),
      D = T.useRef(null),
      Y = T.useRef(),
      [Z, U] = T.useState(0),
      [q, Q] = T.useState({}),
      [W, re] = T.useState(""),
      ue = async () => {
        try {
          const g = new URLSearchParams({
              page: N.page,
              limit: N.rowsPerPage,
              search: N.search,
            }),
            J = await (
              await fetch(
                `/backend/includes/get_csv_list.php?${g}`
              )
            ).json();
          R(Array.isArray(J.data) ? J.data : []),
            L((K) => ({ ...K, total: J.total || 0 }));
        } catch (g) {
          console.error("Error fetching lists:", g),
            R([]),
            L((M) => ({ ...M, total: 0 })),
            c({ type: "error", message: "Failed to load lists" });
        }
      },
      ce = async () => {
        try {
          const M = await (
            await fetch(
              "/backend/includes/get_results.php?retry_failed=1"
            )
          ).json();
          M.status === "success" ? U(M.total) : U(0);
        } catch (g) {
          console.error("Error fetching retry failed count:", g), U(0);
        }
      };
    T.useEffect(() => {
      ce();
    }, [v]),
      T.useEffect(() => {
        const g = setInterval(() => {
          ue(), ce();
        }, 5e3);
        return () => clearInterval(g);
      }, []),
      T.useEffect(() => {
        ue();
      }, [N.page, N.rowsPerPage, N.search]);
    const je = (g) => {
        const { name: M, value: J } = g.target;
        u((K) => ({ ...K, [M]: J }));
      },
      Ue = 5 * 1024 * 1024,
      Me = (g) => {
        const M = g.target.files[0];
        if (M && M.size > Ue) {
          c({ type: "error", message: "CSV file size must be 5 MB or less." });
          return;
        }
        u((J) => ({ ...J, csvFile: M }));
      },
      F = async (g) => {
        if (
          (g.preventDefault(),
          c(null),
          !s.csvFile || !s.listName || !s.fileName)
        ) {
          c({ type: "error", message: "All fields are required" });
          return;
        }
        if (
          (await s.csvFile.text()).split(/\r?\n/).filter((P) => P.trim() !== "")
            .length < 2
        ) {
          c({
            type: "error",
            message: "CSV file must contain at least one data row.",
          });
          return;
        }
        const K = new FormData();
        K.append("csv_file", s.csvFile),
          K.append("list_name", s.listName),
          K.append("file_name", s.fileName);
        try {
          m(!0);
          const le = await (
            await fetch(
              "/backend/routes/api.php/api/upload",
              { method: "POST", body: K }
            )
          ).json();
          le.status === "success"
            ? (c({
                type: "success",
                message: le.message || "Upload successful",
              }),
              p(!0),
              pe(),
              u({ listName: "", fileName: "", csvFile: null }),
              ue(),
              ce())
            : c({ type: "error", message: le.message || "Upload failed" });
        } catch (P) {
          console.error("Error uploading file:", P),
            c({ type: "error", message: "Network error" });
        } finally {
          m(!1);
        }
      },
      oe = async (g, M) => {
        try {
          const J = `/backend/includes/get_results.php?export=${g}&csv_list_id=${M}`,
            P = await (await fetch(J)).blob(),
            le = window.URL.createObjectURL(P),
            te = document.createElement("a");
          (te.href = le),
            (te.download = `${g}_emails_list_${M}.csv`),
            document.body.appendChild(te),
            te.click(),
            te.remove(),
            c({ type: "success", message: `Exported ${g} emails list` });
        } catch {
          c({ type: "error", message: `Failed to export ${g} emails` });
        }
      },
      pe = () => {
        D.current && clearInterval(D.current),
          (D.current = setInterval(async () => {
            try {
              const M = await (await fetch("/api/verify/progress")).json();
              b(M),
                ue(),
                M.total > 0 &&
                  M.processed >= M.total &&
                  (clearInterval(D.current),
                  setTimeout(() => {
                    p(!1),
                      c({
                        type: "success",
                        message: "Verification completed!",
                      }),
                      ue();
                  }, 1e3));
            } catch (g) {
              console.error("Error fetching progress:", g),
                clearInterval(D.current),
                p(!1);
            }
          }, 2e3));
      },
      S = ({ status: g, onClose: M }) =>
        g &&
        r.jsxs("div", {
          className: `
          fixed top-6 left-1/2 transform -translate-x-1/2 z-50
          px-6 py-3 rounded-xl shadow text-base font-semibold
          flex items-center gap-3
          transition-all duration-300
          backdrop-blur-md
          ${
            g.type === "error"
              ? "bg-red-200/60 border border-red-400 text-red-800"
              : "bg-green-200/60 border border-green-400 text-green-800"
          }
        `,
          style: {
            minWidth: 250,
            maxWidth: 400,
            boxShadow: "0 8px 32px 0 rgba(0, 0, 0, 0.23)",
            background:
              g.type === "error"
                ? "rgba(255, 0, 0, 0.29)"
                : "rgba(0, 200, 83, 0.29)",
            borderRadius: "16px",
            backdropFilter: "blur(8px)",
            WebkitBackdropFilter: "blur(8px)",
          },
          role: "alert",
          children: [
            r.jsx("i", {
              className: `fas text-lg ${
                g.type === "error"
                  ? "fa-exclamation-circle text-red-500"
                  : "fa-check-circle text-green-500"
              }`,
            }),
            r.jsx("span", { className: "flex-1", children: g.message }),
            r.jsx("button", {
              onClick: M,
              className:
                "ml-2 text-gray-500 hover:text-gray-700 focus:outline-none",
              "aria-label": "Close",
              children: r.jsx("i", { className: "fas fa-times" }),
            }),
          ],
        });
    T.useEffect(() => {
      if (o) {
        const g = setTimeout(() => c(null), 4e3);
        return () => clearTimeout(g);
      }
    }, [o]),
      T.useEffect(() => {
        let g;
        return (
          y &&
            (g = setInterval(() => {
              ue();
            }, 2e3)),
          () => clearInterval(g)
        );
      }, [y]);
    const H = (g) => {
      const M = g.target.value;
      re(M),
        clearTimeout(Y.current),
        (Y.current = setTimeout(() => {
          L((J) => ({ ...J, search: M, page: 1 }));
        }, 400));
    };
    T.useEffect(() => {
      re(N.search);
    }, [N.search]);
    const $ = async (g) => {
        Q((M) => ({ ...M, [g]: !0 })), c(null);
        try {
          const J = await (
            await fetch(
              `/backend/includes/get_results.php?retry_failed=1&csv_list_id=${g}`
            )
          ).json();
          if (!J.total || J.total === 0) {
            c({
              type: "error",
              message: "No failed emails to retry for this list",
            }),
              Q((le) => ({ ...le, [g]: !1 }));
            return;
          }
          const P = await (
            await fetch(
              `/backend/includes/retry_smtp.php?csv_list_id=${g}`,
              { method: "POST" }
            )
          ).json();
          if (P.status !== "success")
            throw new Error(P.message || "Failed to start retry");
          c({
            type: "success",
            message: `Retry started for ${J.total} emails in list ${g}`,
          }),
            ue();
        } catch (M) {
          c({ type: "error", message: M.message });
        } finally {
          Q((M) => ({ ...M, [g]: !1 }));
        }
      },
      ee = (g) => {
        switch (g) {
          case "completed":
            return "bg-emerald-100 text-emerald-800";
          case "running":
            return "bg-blue-100 text-blue-800";
          case "pending":
            return "bg-amber-100 text-amber-800";
          case "failed":
            return "bg-red-100 text-red-800";
          default:
            return "bg-gray-100 text-gray-800";
        }
      };
    return (
      v.filter((g) => g.domain_status === 2).length,
      r.jsxs("div", {
        className: "container mx-auto px-4 py-8 max-w-7xl",
        children: [
          r.jsx(S, { status: o, onClose: () => c(null) }),
          r.jsxs("div", {
            className:
              "bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8 mt-12",
            children: [
              r.jsxs("div", {
                className: "flex items-center mb-6",
                children: [
                  r.jsx("div", {
                    className: "bg-blue-100 p-2 rounded-lg mr-4",
                    children: r.jsx("svg", {
                      className: "w-6 h-6 text-blue-600",
                      fill: "none",
                      stroke: "currentColor",
                      viewBox: "0 0 24 24",
                      children: r.jsx("path", {
                        strokeLinecap: "round",
                        strokeLinejoin: "round",
                        strokeWidth: "2",
                        d: "M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10",
                      }),
                    }),
                  }),
                  r.jsx("h2", {
                    className: "text-xl font-semibold text-gray-800",
                    children: "Upload Email List",
                  }),
                ],
              }),
              r.jsxs("form", {
                onSubmit: F,
                className: "space-y-6",
                children: [
                  r.jsxs("div", {
                    className: "grid grid-cols-1 md:grid-cols-2 gap-6",
                    children: [
                      r.jsxs("div", {
                        children: [
                          r.jsxs("label", {
                            className:
                              "block text-sm font-medium text-gray-700 mb-2",
                            children: [
                              "List Name",
                              r.jsx("span", {
                                className: "text-red-500 ml-1",
                                children: "*",
                              }),
                            ],
                          }),
                          r.jsx("input", {
                            type: "text",
                            name: "listName",
                            value: s.listName,
                            onChange: je,
                            className:
                              "w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition-colors",
                            placeholder: "e.g. List_2025",
                            required: !0,
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        children: [
                          r.jsxs("label", {
                            className:
                              "block text-sm font-medium text-gray-700 mb-2",
                            children: [
                              "File Name",
                              r.jsx("span", {
                                className: "text-red-500 ml-1",
                                children: "*",
                              }),
                            ],
                          }),
                          r.jsx("input", {
                            type: "text",
                            name: "fileName",
                            value: s.fileName,
                            onChange: je,
                            className:
                              "w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition-colors",
                            placeholder: "e.g. File_2025.csv",
                            required: !0,
                          }),
                        ],
                      }),
                    ],
                  }),
                  r.jsxs("div", {
                    children: [
                      r.jsxs("label", {
                        className:
                          "block text-sm font-medium text-gray-700 mb-2",
                        children: [
                          "CSV File",
                          r.jsx("span", {
                            className: "text-red-500 ml-1",
                            children: "*",
                          }),
                        ],
                      }),
                      r.jsx("div", {
                        className:
                          "mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-blue-400 transition-colors",
                        children: r.jsx("div", {
                          className: "space-y-1 text-center",
                          children: s.csvFile
                            ? r.jsxs("div", {
                                className: "flex items-center justify-center",
                                children: [
                                  r.jsx("div", {
                                    className:
                                      "bg-green-100 p-2 rounded-lg mr-4",
                                    children: r.jsx("svg", {
                                      className: "w-6 h-6 text-green-600",
                                      fill: "none",
                                      stroke: "currentColor",
                                      viewBox: "0 0 24 24",
                                      children: r.jsx("path", {
                                        strokeLinecap: "round",
                                        strokeLinejoin: "round",
                                        strokeWidth: "2",
                                        d: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z",
                                      }),
                                    }),
                                  }),
                                  r.jsxs("div", {
                                    className: "text-left",
                                    children: [
                                      r.jsx("p", {
                                        className:
                                          "text-sm font-medium text-gray-700",
                                        children: s.csvFile.name,
                                      }),
                                      r.jsxs("p", {
                                        className: "text-xs text-gray-500",
                                        children: [
                                          (s.csvFile.size / 1024).toFixed(1),
                                          " KB",
                                        ],
                                      }),
                                    ],
                                  }),
                                ],
                              })
                            : r.jsxs(r.Fragment, {
                                children: [
                                  r.jsx("svg", {
                                    className:
                                      "mx-auto h-12 w-12 text-gray-400",
                                    fill: "none",
                                    stroke: "currentColor",
                                    viewBox: "0 0 24 24",
                                    children: r.jsx("path", {
                                      strokeLinecap: "round",
                                      strokeLinejoin: "round",
                                      strokeWidth: "2",
                                      d: "M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12",
                                    }),
                                  }),
                                  r.jsxs("div", {
                                    className:
                                      "flex text-sm text-gray-600 justify-center",
                                    children: [
                                      r.jsxs("label", {
                                        className:
                                          "relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none",
                                        children: [
                                          r.jsx("span", {
                                            children: "Upload a file",
                                          }),
                                          r.jsx("input", {
                                            type: "file",
                                            name: "csvFile",
                                            className: "sr-only",
                                            accept: ".csv",
                                            onChange: Me,
                                            required: !0,
                                          }),
                                        ],
                                      }),
                                      r.jsx("p", {
                                        className: "pl-1",
                                        children: "or drag and drop",
                                      }),
                                    ],
                                  }),
                                  r.jsx("p", {
                                    className: "text-xs text-gray-500",
                                    children: "Only 5MB CSV files",
                                  }),
                                ],
                              }),
                        }),
                      }),
                    ],
                  }),
                  r.jsx("div", {
                    className: "flex justify-center",
                    children: r.jsx("button", {
                      type: "submit",
                      disabled: f,
                      className:
                        "px-6 py-3 bg-blue-600 text-white font-medium rounded-lg shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors flex items-center disabled:opacity-70",
                      children: f
                        ? r.jsxs(r.Fragment, {
                            children: [
                              r.jsxs("svg", {
                                className:
                                  "animate-spin -ml-1 mr-3 h-5 w-5 text-white",
                                xmlns: "http://www.w3.org/2000/svg",
                                fill: "none",
                                viewBox: "0 0 24 24",
                                children: [
                                  r.jsx("circle", {
                                    className: "opacity-25",
                                    cx: "12",
                                    cy: "12",
                                    r: "10",
                                    stroke: "currentColor",
                                    strokeWidth: "4",
                                  }),
                                  r.jsx("path", {
                                    className: "opacity-75",
                                    fill: "currentColor",
                                    d: "M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z",
                                  }),
                                ],
                              }),
                              "Processing...",
                            ],
                          })
                        : r.jsxs(r.Fragment, {
                            children: [
                              r.jsx("svg", {
                                className: "w-5 h-5 mr-2 -ml-1",
                                fill: "none",
                                stroke: "currentColor",
                                viewBox: "0 0 24 24",
                                children: r.jsx("path", {
                                  strokeLinecap: "round",
                                  strokeLinejoin: "round",
                                  strokeWidth: "2",
                                  d: "M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10",
                                }),
                              }),
                              "Upload & Verify",
                            ],
                          }),
                    }),
                  }),
                ],
              }),
            ],
          }),
          r.jsxs("div", {
            className:
              "bg-white rounded-xl shadow-sm border border-gray-200 p-6",
            children: [
              r.jsxs("div", {
                className:
                  "flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4",
                children: [
                  r.jsxs("div", {
                    className: "flex items-center",
                    children: [
                      r.jsx("div", {
                        className: "bg-blue-100 p-2 rounded-lg mr-4",
                        children: r.jsx("svg", {
                          className: "w-6 h-6 text-blue-600",
                          fill: "none",
                          stroke: "currentColor",
                          viewBox: "0 0 24 24",
                          children: r.jsx("path", {
                            strokeLinecap: "round",
                            strokeLinejoin: "round",
                            strokeWidth: "2",
                            d: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2",
                          }),
                        }),
                      }),
                      r.jsx("h2", {
                        className: "text-xl font-semibold text-gray-800",
                        children: "Email Lists",
                      }),
                    ],
                  }),
                  r.jsxs("div", {
                    className:
                      "flex flex-col sm:flex-row gap-3 w-full sm:w-auto",
                    children: [
                      r.jsxs("div", {
                        className: "relative flex-grow max-w-md",
                        children: [
                          r.jsx("div", {
                            className:
                              "absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none",
                            children: r.jsx("svg", {
                              className: "h-5 w-5 text-gray-400",
                              fill: "none",
                              stroke: "currentColor",
                              viewBox: "0 0 24 24",
                              children: r.jsx("path", {
                                strokeLinecap: "round",
                                strokeLinejoin: "round",
                                strokeWidth: "2",
                                d: "M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z",
                              }),
                            }),
                          }),
                          r.jsx("input", {
                            type: "text",
                            placeholder: "Search lists...",
                            className:
                              "pl-10 w-full border border-gray-300 rounded-lg py-2 px-4 focus:ring-blue-500 focus:border-blue-500 transition-colors",
                            value: W,
                            onChange: H,
                          }),
                        ],
                      }),
                      r.jsx("div", { className: "flex gap-2" }),
                    ],
                  }),
                ],
              }),
              r.jsx("div", {
                className: "overflow-x-auto rounded-lg border border-gray-200",
                children: r.jsxs("table", {
                  className: "min-w-full divide-y divide-gray-200",
                  children: [
                    r.jsx("thead", {
                      className: "bg-gray-50",
                      children: r.jsxs("tr", {
                        children: [
                          r.jsx("th", {
                            className:
                              "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                            children: "ID",
                          }),
                          r.jsx("th", {
                            className:
                              "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                            children: "List Name",
                          }),
                          r.jsx("th", {
                            className:
                              "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                            children: "Status",
                          }),
                          r.jsx("th", {
                            className:
                              "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                            children: "Emails",
                          }),
                          r.jsx("th", {
                            className:
                              "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                            children: "Valid/Invalid",
                          }),
                          r.jsx("th", {
                            className:
                              "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                            children: "Actions",
                          }),
                        ],
                      }),
                    }),
                    r.jsx("tbody", {
                      className: "bg-white divide-y divide-gray-200",
                      children:
                        v.length === 0
                          ? r.jsx("tr", {
                              children: r.jsx("td", {
                                colSpan: 6,
                                className:
                                  "px-6 py-4 text-center text-gray-500 text-sm",
                                children: N.search
                                  ? "No lists match your search criteria"
                                  : "No lists found. Upload a CSV file to get started.",
                              }),
                            })
                          : v
                              .filter((g) =>
                                g.list_name
                                  .toLowerCase()
                                  .includes(N.search.toLowerCase())
                              )
                              .map((g) =>
                                r.jsxs(
                                  "tr",
                                  {
                                    className:
                                      "hover:bg-gray-50 transition-colors",
                                    children: [
                                      r.jsx("td", {
                                        className:
                                          "px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900",
                                        children: g.id,
                                      }),
                                      r.jsx("td", {
                                        className:
                                          "px-6 py-4 whitespace-nowrap text-sm text-gray-900",
                                        children: g.list_name,
                                      }),
                                      r.jsx("td", {
                                        className:
                                          "px-6 py-4 whitespace-nowrap",
                                        children: r.jsx("span", {
                                          className: `px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full ${ee(
                                            g.status
                                          )}`,
                                          children:
                                            g.status.charAt(0).toUpperCase() +
                                            g.status.slice(1),
                                        }),
                                      }),
                                      r.jsxs("td", {
                                        className:
                                          "px-6 py-4 whitespace-nowrap text-sm text-gray-500",
                                        children: [g.total_emails, " total"],
                                      }),
                                      r.jsxs("td", {
                                        className:
                                          "px-6 py-4 whitespace-nowrap text-sm",
                                        children: [
                                          r.jsxs("span", {
                                            className:
                                              "text-emerald-600 font-medium",
                                            children: [
                                              g.valid_count || 0,
                                              " valid",
                                            ],
                                          }),
                                          " ",
                                          "/",
                                          " ",
                                          r.jsxs("span", {
                                            className:
                                              "text-red-600 font-medium",
                                            children: [
                                              g.invalid_count || 0,
                                              " invalid",
                                            ],
                                          }),
                                        ],
                                      }),
                                      r.jsxs("td", {
                                        className:
                                          "px-6 py-4 whitespace-nowrap text-sm font-medium flex gap-2",
                                        children: [
                                          r.jsxs("button", {
                                            onClick: () => C(g.id),
                                            className:
                                              "text-blue-600 hover:text-blue-800 transition-colors flex items-center",
                                            children: [
                                              r.jsxs("svg", {
                                                className: "w-4 h-4 mr-1",
                                                fill: "none",
                                                stroke: "currentColor",
                                                viewBox: "0 0 24 24",
                                                children: [
                                                  r.jsx("path", {
                                                    strokeLinecap: "round",
                                                    strokeLinejoin: "round",
                                                    strokeWidth: "2",
                                                    d: "M15 12a3 3 0 11-6 0 3 3 0 016 0z",
                                                  }),
                                                  r.jsx("path", {
                                                    strokeLinecap: "round",
                                                    strokeLinejoin: "round",
                                                    strokeWidth: "2",
                                                    d: "M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z",
                                                  }),
                                                ],
                                              }),
                                              "View",
                                            ],
                                          }),
                                          r.jsxs("button", {
                                            onClick: () => oe("valid", g.id),
                                            className:
                                              "text-green-600 hover:text-green-800 transition-colors flex items-center",
                                            children: [
                                              r.jsx("svg", {
                                                className: "w-4 h-4 mr-1",
                                                fill: "none",
                                                stroke: "currentColor",
                                                viewBox: "0 0 24 24",
                                                children: r.jsx("path", {
                                                  strokeLinecap: "round",
                                                  strokeLinejoin: "round",
                                                  strokeWidth: "2",
                                                  d: "M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4",
                                                }),
                                              }),
                                              "Valid",
                                            ],
                                          }),
                                          r.jsxs("button", {
                                            onClick: () => oe("invalid", g.id),
                                            className:
                                              "text-red-600 hover:text-red-800 transition-colors flex items-center",
                                            children: [
                                              r.jsx("svg", {
                                                className: "w-4 h-4 mr-1",
                                                fill: "none",
                                                stroke: "currentColor",
                                                viewBox: "0 0 24 24",
                                                children: r.jsx("path", {
                                                  strokeLinecap: "round",
                                                  strokeLinejoin: "round",
                                                  strokeWidth: "2",
                                                  d: "M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4",
                                                }),
                                              }),
                                              "Invalid",
                                            ],
                                          }),
                                          r.jsxs("button", {
                                            onClick: () => $(g.id),
                                            disabled:
                                              q[g.id] || !g.failed_count,
                                            className:
                                              "text-yellow-600 hover:text-yellow-800 transition-colors flex items-center border border-yellow-300 rounded px-2 py-1 disabled:opacity-60",
                                            title:
                                              "Retry failed emails for this list",
                                            children: [
                                              r.jsx("svg", {
                                                className: `w-4 h-4 mr-1 ${
                                                  q[g.id] ? "animate-spin" : ""
                                                }`,
                                                fill: "none",
                                                stroke: "currentColor",
                                                viewBox: "0 0 24 24",
                                                children: r.jsx("path", {
                                                  strokeLinecap: "round",
                                                  strokeLinejoin: "round",
                                                  strokeWidth: "2",
                                                  d: "M4 4v5h5M20 20v-5h-5M5.5 8.5a8 8 0 0113 0M18.5 15.5a8 8 0 01-13 0",
                                                }),
                                              }),
                                              q[g.id]
                                                ? "Retrying..."
                                                : `Retry (${
                                                    g.failed_count || 0
                                                  })`,
                                            ],
                                          }),
                                        ],
                                      }),
                                    ],
                                  },
                                  g.id
                                )
                              ),
                    }),
                  ],
                }),
              }),
              v.length > 0 &&
                r.jsxs("div", {
                  className:
                    "flex flex-col items-center justify-center mt-6 px-1 gap-2",
                  children: [
                    r.jsxs("div", {
                      className: "text-sm text-gray-500 mb-2",
                      children: [
                        "Showing",
                        " ",
                        r.jsx("span", {
                          className: "font-medium",
                          children: (N.page - 1) * N.rowsPerPage + 1,
                        }),
                        " ",
                        "to",
                        " ",
                        r.jsx("span", {
                          className: "font-medium",
                          children: Math.min(N.page * N.rowsPerPage, N.total),
                        }),
                        " ",
                        "of ",
                        r.jsx("span", {
                          className: "font-medium",
                          children: N.total,
                        }),
                        " ",
                        "lists",
                      ],
                    }),
                    r.jsxs("div", {
                      className: "flex items-center gap-2",
                      children: [
                        r.jsx("button", {
                          onClick: () => L((g) => ({ ...g, page: 1 })),
                          disabled: N.page === 1,
                          className:
                            "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors font-bold text-gray-900",
                          children: r.jsx("svg", {
                            className: "w-5 h-5 text-gray-900",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24",
                            children: r.jsx("path", {
                              strokeLinecap: "round",
                              strokeLinejoin: "round",
                              strokeWidth: "2",
                              d: "M11 19l-7-7 7-7m8 14l-7-7 7-7",
                            }),
                          }),
                        }),
                        r.jsx("button", {
                          onClick: () => L((g) => ({ ...g, page: g.page - 1 })),
                          disabled: N.page === 1,
                          className:
                            "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors font-bold text-gray-900",
                          children: r.jsx("svg", {
                            className: "w-5 h-5 text-gray-900",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24",
                            children: r.jsx("path", {
                              strokeLinecap: "round",
                              strokeLinejoin: "round",
                              strokeWidth: "2",
                              d: "M15 19l-7-7 7-7",
                            }),
                          }),
                        }),
                        r.jsxs("span", {
                          className: "text-sm font-bold text-gray-900",
                          children: [
                            "Page ",
                            N.page,
                            " of",
                            " ",
                            Math.max(1, Math.ceil(N.total / N.rowsPerPage)),
                          ],
                        }),
                        r.jsx("button", {
                          onClick: () =>
                            L((g) => ({
                              ...g,
                              page: Math.min(
                                Math.ceil(N.total / N.rowsPerPage),
                                g.page + 1
                              ),
                            })),
                          disabled:
                            N.page >= Math.ceil(N.total / N.rowsPerPage),
                          className:
                            "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors font-bold text-gray-900",
                          children: r.jsx("svg", {
                            className: "w-5 h-5 text-gray-900",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24",
                            children: r.jsx("path", {
                              strokeLinecap: "round",
                              strokeLinejoin: "round",
                              strokeWidth: "2",
                              d: "M9 5l7 7-7 7",
                            }),
                          }),
                        }),
                        r.jsx("button", {
                          onClick: () =>
                            L((g) => ({
                              ...g,
                              page: Math.ceil(N.total / N.rowsPerPage),
                            })),
                          disabled:
                            N.page >= Math.ceil(N.total / N.rowsPerPage),
                          className:
                            "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors font-bold text-gray-900",
                          children: r.jsx("svg", {
                            className: "w-5 h-5 text-gray-900",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24",
                            children: r.jsx("path", {
                              strokeLinecap: "round",
                              strokeLinejoin: "round",
                              strokeWidth: "2",
                              d: "M13 5l7 7-7 7M5 5l7 7-7 7",
                            }),
                          }),
                        }),
                        r.jsx("select", {
                          value: N.rowsPerPage,
                          onChange: (g) =>
                            L((M) => ({
                              ...M,
                              rowsPerPage: Number(g.target.value),
                              page: 1,
                            })),
                          className:
                            "border p-2 rounded-lg text-sm bg-white focus:ring-blue-500 focus:border-blue-500 transition-colors",
                          children: [10, 25, 50, 100].map((g) =>
                            r.jsx("option", { value: g, children: g }, g)
                          ),
                        }),
                      ],
                    }),
                  ],
                }),
            ],
          }),
          w && r.jsx(Xg, { listId: w, onClose: () => C(null) }),
        ],
      })
    );
  },
  xi = "/backend/routes/api.php/api/master/smtps",
  gi = {
    name: "",
    host: "",
    port: 465,
    encryption: "ssl",
    email: "",
    password: "",
    daily_limit: 500,
    hourly_limit: 100,
    is_active: !0,
  },
  Zg = ({ status: s, onClose: u }) =>
    s &&
    r.jsxs("div", {
      className: `
        fixed top-6 left-1/2 transform -translate-x-1/2 z-50
        px-6 py-3 rounded-xl shadow text-base font-semibold
        flex items-center gap-3
        transition-all duration-300
        backdrop-blur-md
        ${
          s.type === "error"
            ? "bg-red-200/60 border border-red-400 text-red-800"
            : "bg-green-200/60 border border-green-400 text-green-800"
        }
      `,
      style: {
        minWidth: 250,
        maxWidth: 400,
        boxShadow: "0 8px 32px 0 rgba(0, 0, 0, 0.23)",
        background:
          s.type === "error"
            ? "rgba(255, 0, 0, 0.29)"
            : "rgba(0, 200, 83, 0.29)",
        borderRadius: "16px",
        backdropFilter: "blur(8px)",
        WebkitBackdropFilter: "blur(8px)",
      },
      role: "alert",
      children: [
        r.jsx("i", {
          className: `fas text-lg ${
            s.type === "error"
              ? "fa-exclamation-circle text-red-500"
              : "fa-check-circle text-green-500"
          }`,
        }),
        r.jsx("span", { className: "flex-1", children: s.message }),
        r.jsx("button", {
          onClick: u,
          className:
            "ml-2 text-gray-500 hover:text-gray-700 focus:outline-none",
          "aria-label": "Close",
          children: r.jsx("i", { className: "fas fa-times" }),
        }),
      ],
    }),
  Kg = () => {
    const [s, u] = T.useState([]),
      [o, c] = T.useState(!0),
      [f, m] = T.useState(!1),
      [x, b] = T.useState(!1),
      [y, p] = T.useState(gi),
      [v, R] = T.useState(null),
      [N, L] = T.useState(null),
      w = async () => {
        c(!0);
        try {
          const Q = await (await fetch(xi)).json();
          Array.isArray(Q.data) ? u(Q.data) : Array.isArray(Q) ? u(Q) : u([]);
        } catch {
          L({ type: "error", message: "Failed to load SMTP servers." }), u([]);
        }
        c(!1);
      };
    T.useEffect(() => {
      w();
    }, []);
    const C = (q) => {
        const { name: Q, value: W, type: re, checked: ue } = q.target;
        p((ce) => ({ ...ce, [Q]: re === "checkbox" ? ue : W }));
      },
      D = async (q) => {
        q.preventDefault();
        try {
          const W = await (
            await fetch(xi, {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(y),
            })
          ).json();
          W.success
            ? (L({
                type: "success",
                message: "SMTP server added successfully!",
              }),
              m(!1),
              p(gi),
              w())
            : L({
                type: "error",
                message: W.message || "Failed to add server.",
              });
        } catch {
          L({ type: "error", message: "Failed to add server." });
        }
      },
      Y = (q) => {
        R(q.id), p({ ...q, is_active: !!q.is_active }), b(!0);
      },
      Z = async (q) => {
        q.preventDefault();
        try {
          const W = await (
            await fetch(`${xi}?id=${v}`, {
              method: "PUT",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(y),
            })
          ).json();
          W.success
            ? (L({
                type: "success",
                message: "SMTP server updated successfully!",
              }),
              b(!1),
              p(gi),
              R(null),
              w())
            : L({
                type: "error",
                message: W.message || "Failed to update server.",
              });
        } catch {
          L({ type: "error", message: "Failed to update server." });
        }
      },
      U = async (q) => {
        if (window.confirm("Are you sure you want to delete this SMTP server?"))
          try {
            const W = await (
              await fetch(`${xi}?id=${q}`, { method: "DELETE" })
            ).json();
            W.success
              ? (L({
                  type: "success",
                  message: "SMTP server deleted successfully!",
                }),
                w())
              : L({
                  type: "error",
                  message: W.message || "Failed to delete server.",
                });
          } catch {
            L({ type: "error", message: "Failed to delete server." });
          }
      };
    return (
      T.useEffect(() => {
        if (N) {
          const q = setTimeout(() => L(null), 3e3);
          return () => clearTimeout(q);
        }
      }, [N]),
      r.jsxs("main", {
        className: "max-w-7xl mx-auto px-4 mt-14 sm:px-6 py-6",
        children: [
          r.jsx(Zg, { status: N, onClose: () => L(null) }),
          r.jsxs("div", {
            className: "flex justify-between items-center mb-6",
            children: [
              r.jsxs("h1", {
                className: "text-2xl font-bold text-gray-900 flex items-center",
                children: [
                  r.jsx("i", {
                    className: "fas fa-server mr-3 text-indigo-600",
                  }),
                  "SMTP Records",
                ],
              }),
              r.jsxs("button", {
                onClick: () => {
                  p(gi), m(!0);
                },
                className:
                  "inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500",
                children: [
                  r.jsx("i", { className: "fas fa-plus mr-2" }),
                  " Add SMTP Server",
                ],
              }),
            ],
          }),
          r.jsx("div", {
            className: "card overflow-hidden bg-white rounded-xl shadow",
            children: r.jsx("div", {
              className: "overflow-x-auto",
              children: r.jsxs("table", {
                className: "min-w-full divide-y divide-gray-200",
                children: [
                  r.jsx("thead", {
                    className: "bg-gray-50",
                    children: r.jsxs("tr", {
                      children: [
                        r.jsx("th", {
                          className:
                            "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                          children: "Name",
                        }),
                        r.jsx("th", {
                          className:
                            "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                          children: "Host",
                        }),
                        r.jsx("th", {
                          className:
                            "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                          children: "Email",
                        }),
                        r.jsx("th", {
                          className:
                            "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                          children: "Status",
                        }),
                        r.jsx("th", {
                          className:
                            "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                          children: "Hourly Limit",
                        }),
                        r.jsx("th", {
                          className:
                            "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                          children: "Daily Limit",
                        }),
                        r.jsx("th", {
                          className:
                            "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                          children: "Actions",
                        }),
                      ],
                    }),
                  }),
                  r.jsx("tbody", {
                    className: "bg-white divide-y divide-gray-200",
                    children: o
                      ? r.jsx("tr", {
                          children: r.jsx("td", {
                            colSpan: 7,
                            className:
                              "px-6 py-4 text-center text-sm text-gray-500",
                            children: "Loading...",
                          }),
                        })
                      : s.length === 0
                      ? r.jsx("tr", {
                          children: r.jsx("td", {
                            colSpan: 7,
                            className:
                              "px-6 py-4 text-center text-sm text-gray-500",
                            children:
                              "No SMTP servers found. Add one to get started.",
                          }),
                        })
                      : s.map((q) => {
                          var Q;
                          return r.jsxs(
                            "tr",
                            {
                              children: [
                                r.jsx("td", {
                                  className: "px-6 py-4 whitespace-nowrap",
                                  children: r.jsx("div", {
                                    className: "flex items-center",
                                    children: r.jsx("div", {
                                      className:
                                        "text-sm font-medium text-gray-900",
                                      children: q.name,
                                    }),
                                  }),
                                }),
                                r.jsxs("td", {
                                  className: "px-6 py-4 whitespace-nowrap",
                                  children: [
                                    r.jsx("div", {
                                      className: "text-sm text-gray-900",
                                      children: q.host,
                                    }),
                                    r.jsxs("div", {
                                      className: "text-sm text-gray-500",
                                      children: [
                                        "Port: ",
                                        q.port,
                                        " (",
                                        ((Q = q.encryption) == null
                                          ? void 0
                                          : Q.toUpperCase()) || "None",
                                        ")",
                                      ],
                                    }),
                                  ],
                                }),
                                r.jsx("td", {
                                  className:
                                    "px-6 py-4 whitespace-nowrap text-sm text-gray-500",
                                  children: q.email,
                                }),
                                r.jsx("td", {
                                  className: "px-6 py-4 whitespace-nowrap",
                                  children: r.jsx("span", {
                                    className: `px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                      q.is_active
                                        ? "bg-green-100 text-green-700"
                                        : "bg-red-100 text-red-700"
                                    }`,
                                    children: q.is_active
                                      ? "Active"
                                      : "Inactive",
                                  }),
                                }),
                                r.jsx("td", {
                                  className:
                                    "px-6 py-4 whitespace-nowrap text-sm text-gray-500",
                                  children: q.hourly_limit,
                                }),
                                r.jsx("td", {
                                  className:
                                    "px-6 py-4 whitespace-nowrap text-sm text-gray-500",
                                  children: q.daily_limit,
                                }),
                                r.jsxs("td", {
                                  className:
                                    "px-6 py-4 whitespace-nowrap text-sm font-medium",
                                  children: [
                                    r.jsx("button", {
                                      onClick: () => Y(q),
                                      className:
                                        "text-indigo-600 hover:text-indigo-900 mr-3",
                                      children: r.jsx("i", {
                                        className: "fas fa-edit mr-1",
                                      }),
                                    }),
                                    r.jsx("button", {
                                      onClick: () => U(q.id),
                                      className:
                                        "text-red-600 hover:text-red-900",
                                      children: r.jsx("i", {
                                        className: "fas fa-trash mr-1",
                                      }),
                                    }),
                                  ],
                                }),
                              ],
                            },
                            q.id
                          );
                        }),
                  }),
                ],
              }),
            }),
          }),
          f &&
            r.jsx("div", {
              className:
                "fixed inset-0 bg-black/30 backdrop-blur-md backdrop-saturate-150 border border-white/20 shadow-xl overflow-y-auto h-full w-full z-50 flex items-center justify-center",
              children: r.jsxs("div", {
                className:
                  "relative mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white",
                children: [
                  r.jsxs("div", {
                    className: "flex justify-between items-center mb-4",
                    children: [
                      r.jsxs("h3", {
                        className: "text-lg font-medium text-gray-900",
                        children: [
                          r.jsx("i", {
                            className:
                              "fas fa-plus-circle mr-2 text-indigo-600",
                          }),
                          "Add New SMTP Server",
                        ],
                      }),
                      r.jsx("button", {
                        onClick: () => m(!1),
                        className: "text-gray-400 hover:text-gray-500",
                        children: r.jsx("i", { className: "fas fa-times" }),
                      }),
                    ],
                  }),
                  r.jsxs("form", {
                    className: "space-y-4",
                    onSubmit: D,
                    children: [
                      r.jsxs("div", {
                        className: "grid grid-cols-1 md:grid-cols-2 gap-4",
                        children: [
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Name",
                              }),
                              r.jsx("input", {
                                type: "text",
                                name: "name",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                placeholder: "SMTP1",
                                value: y.name,
                                onChange: C,
                              }),
                            ],
                          }),
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Host",
                              }),
                              r.jsx("input", {
                                type: "text",
                                name: "host",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                placeholder: "smtp.example.com",
                                value: y.host,
                                onChange: C,
                              }),
                            ],
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        className: "grid grid-cols-1 md:grid-cols-3 gap-4",
                        children: [
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Port",
                              }),
                              r.jsx("input", {
                                type: "number",
                                name: "port",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                placeholder: "465",
                                value: y.port,
                                onChange: C,
                              }),
                            ],
                          }),
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Encryption",
                              }),
                              r.jsxs("select", {
                                name: "encryption",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                value: y.encryption,
                                onChange: C,
                                children: [
                                  r.jsx("option", {
                                    value: "ssl",
                                    children: "SSL",
                                  }),
                                  r.jsx("option", {
                                    value: "tls",
                                    children: "TLS",
                                  }),
                                  r.jsx("option", {
                                    value: "",
                                    children: "None",
                                  }),
                                ],
                              }),
                            ],
                          }),
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Hourly Limit",
                              }),
                              r.jsx("input", {
                                type: "number",
                                name: "hourly_limit",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                placeholder: "100",
                                value: y.hourly_limit,
                                onChange: C,
                              }),
                            ],
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        className: "grid grid-cols-1 md:grid-cols-3 gap-4",
                        children: [
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Daily Limit",
                              }),
                              r.jsx("input", {
                                type: "number",
                                name: "daily_limit",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                placeholder: "500",
                                value: y.daily_limit,
                                onChange: C,
                              }),
                            ],
                          }),
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Email",
                              }),
                              r.jsx("input", {
                                type: "email",
                                name: "email",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                placeholder: "user@example.com",
                                value: y.email,
                                onChange: C,
                              }),
                            ],
                          }),
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Password",
                              }),
                              r.jsx("input", {
                                type: "password",
                                name: "password",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                placeholder: "SMTP password",
                                value: y.password,
                                onChange: C,
                              }),
                            ],
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        className: "flex items-center",
                        children: [
                          r.jsx("input", {
                            type: "checkbox",
                            name: "is_active",
                            id: "is_active",
                            checked: y.is_active,
                            onChange: C,
                            className:
                              "h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded",
                          }),
                          r.jsx("label", {
                            htmlFor: "is_active",
                            className: "ml-2 block text-sm text-gray-700",
                            children: "Active",
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        className: "flex justify-end pt-4 space-x-3",
                        children: [
                          r.jsx("button", {
                            type: "button",
                            onClick: () => m(!1),
                            className:
                              "inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500",
                            children: "Cancel",
                          }),
                          r.jsxs("button", {
                            type: "submit",
                            className:
                              "inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500",
                            children: [
                              r.jsx("i", { className: "fas fa-save mr-2" }),
                              " Save Server",
                            ],
                          }),
                        ],
                      }),
                    ],
                  }),
                ],
              }),
            }),
          x &&
            r.jsx("div", {
              className:
                "fixed inset-0 bg-black/30 backdrop-blur-md backdrop-saturate-150 border border-white/20 shadow-xl overflow-y-auto h-full w-full z-50 flex items-center justify-center",
              children: r.jsxs("div", {
                className:
                  "relative mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white",
                children: [
                  r.jsxs("div", {
                    className: "flex justify-between items-center mb-4",
                    children: [
                      r.jsxs("h3", {
                        className: "text-lg font-medium text-gray-900",
                        children: [
                          r.jsx("i", {
                            className: "fas fa-edit mr-2 text-indigo-600",
                          }),
                          "Edit SMTP Server",
                        ],
                      }),
                      r.jsx("button", {
                        onClick: () => b(!1),
                        className: "text-gray-400 hover:text-gray-500",
                        children: r.jsx("i", { className: "fas fa-times" }),
                      }),
                    ],
                  }),
                  r.jsxs("form", {
                    className: "space-y-4",
                    onSubmit: Z,
                    children: [
                      r.jsx("input", { type: "hidden", name: "id", value: v }),
                      r.jsxs("div", {
                        className: "grid grid-cols-1 md:grid-cols-2 gap-4",
                        children: [
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Name",
                              }),
                              r.jsx("input", {
                                type: "text",
                                name: "name",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                value: y.name,
                                onChange: C,
                              }),
                            ],
                          }),
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Host",
                              }),
                              r.jsx("input", {
                                type: "text",
                                name: "host",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                value: y.host,
                                onChange: C,
                              }),
                            ],
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        className: "grid grid-cols-1 md:grid-cols-3 gap-4",
                        children: [
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Port",
                              }),
                              r.jsx("input", {
                                type: "number",
                                name: "port",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                value: y.port,
                                onChange: C,
                              }),
                            ],
                          }),
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Encryption",
                              }),
                              r.jsxs("select", {
                                name: "encryption",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                value: y.encryption,
                                onChange: C,
                                children: [
                                  r.jsx("option", {
                                    value: "ssl",
                                    children: "SSL",
                                  }),
                                  r.jsx("option", {
                                    value: "tls",
                                    children: "TLS",
                                  }),
                                  r.jsx("option", {
                                    value: "",
                                    children: "None",
                                  }),
                                ],
                              }),
                            ],
                          }),
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Hourly Limit",
                              }),
                              r.jsx("input", {
                                type: "number",
                                name: "hourly_limit",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                value: y.hourly_limit,
                                onChange: C,
                              }),
                            ],
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        className: "grid grid-cols-1 md:grid-cols-3 gap-4",
                        children: [
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Daily Limit",
                              }),
                              r.jsx("input", {
                                type: "number",
                                name: "daily_limit",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                value: y.daily_limit,
                                onChange: C,
                              }),
                            ],
                          }),
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Email",
                              }),
                              r.jsx("input", {
                                type: "email",
                                name: "email",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                value: y.email,
                                onChange: C,
                              }),
                            ],
                          }),
                          r.jsxs("div", {
                            children: [
                              r.jsx("label", {
                                className:
                                  "block text-sm font-medium text-gray-700 mb-1",
                                children: "Password",
                              }),
                              r.jsx("input", {
                                type: "password",
                                name: "password",
                                required: !0,
                                className:
                                  "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm",
                                value: y.password,
                                onChange: C,
                              }),
                            ],
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        className: "flex items-center",
                        children: [
                          r.jsx("input", {
                            type: "checkbox",
                            name: "is_active",
                            id: "edit_is_active",
                            checked: y.is_active,
                            onChange: C,
                            className:
                              "h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded",
                          }),
                          r.jsx("label", {
                            htmlFor: "edit_is_active",
                            className: "ml-2 block text-sm text-gray-700",
                            children: "Active",
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        className: "flex justify-end pt-4 space-x-3",
                        children: [
                          r.jsx("button", {
                            type: "button",
                            onClick: () => b(!1),
                            className:
                              "inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500",
                            children: "Cancel",
                          }),
                          r.jsxs("button", {
                            type: "submit",
                            className:
                              "inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500",
                            children: [
                              r.jsx("i", { className: "fas fa-save mr-2" }),
                              " Update Server",
                            ],
                          }),
                        ],
                      }),
                    ],
                  }),
                ],
              }),
            }),
        ],
      })
    );
  },
  yi = { description: "", mail_subject: "", mail_body: "", attachment: null },
  Jg = ({ message: s, onClose: u }) =>
    s &&
    r.jsxs("div", {
      className: `
        fixed top-6 left-1/2 transform -translate-x-1/2 z-50
        px-6 py-3 rounded-xl shadow text-base font-semibold
        flex items-center gap-3
        transition-all duration-300
        backdrop-blur-md
        ${
          s.type === "error"
            ? "bg-red-200/60 border border-red-400 text-red-800"
            : "bg-green-200/60 border border-green-400 text-green-800"
        }
      `,
      style: {
        minWidth: 250,
        maxWidth: 400,
        boxShadow: "0 8px 32px 0 rgba(0, 0, 0, 0.23)",
        background:
          s.type === "error"
            ? "rgba(255, 0, 0, 0.29)"
            : "rgba(0, 200, 83, 0.29)",
        borderRadius: "16px",
        backdropFilter: "blur(8px)",
        WebkitBackdropFilter: "blur(8px)",
      },
      role: "alert",
      children: [
        r.jsx("i", {
          className: `fas text-lg ${
            s.type === "error"
              ? "fa-exclamation-circle text-red-500"
              : "fa-check-circle text-green-500"
          }`,
        }),
        r.jsx("span", { className: "flex-1", children: s.text }),
        r.jsx("button", {
          onClick: u,
          className:
            "ml-2 text-gray-500 hover:text-gray-700 focus:outline-none",
          "aria-label": "Close",
          children: r.jsx("i", { className: "fas fa-times" }),
        }),
      ],
    }),
  $g = () => {
    const [s, u] = T.useState([]),
      [o, c] = T.useState(!0),
      [f, m] = T.useState(!1),
      [x, b] = T.useState(!1),
      [y, p] = T.useState(yi),
      [v, R] = T.useState(null),
      [N, L] = T.useState(null),
      [w, C] = T.useState(null),
      [D, Y] = T.useState({ page: 1, rowsPerPage: 10, total: 0 }),
      Z =
        "/backend/routes/api.php/api/master/campaigns",
      U = async () => {
        c(!0);
        try {
          const oe = await (await fetch(Z)).json();
          Array.isArray(oe)
            ? (u(oe), Y((pe) => ({ ...pe, total: oe.length })))
            : (u([]), Y((pe) => ({ ...pe, total: 0 })));
        } catch {
          C({ type: "error", text: "Failed to load campaigns." }),
            u([]),
            Y((F) => ({ ...F, total: 0 }));
        }
        c(!1);
      };
    T.useEffect(() => {
      U();
    }, []);
    const q = (F) => {
        const { name: oe, value: pe, files: S } = F.target;
        oe === "attachment" ? R(S[0]) : p((H) => ({ ...H, [oe]: pe }));
      },
      Q = async (F) => {
        F.preventDefault();
        const oe = new FormData();
        oe.append("description", y.description),
          oe.append("mail_subject", y.mail_subject),
          oe.append("mail_body", y.mail_body),
          v && oe.append("attachment", v);
        try {
          const S = await (await fetch(Z, { method: "POST", body: oe })).json();
          S.success
            ? (C({ type: "success", text: "Campaign added successfully!" }),
              m(!1),
              p(yi),
              R(null),
              U())
            : C({
                type: "error",
                text: S.message || "Failed to add campaign.",
              });
        } catch {
          C({ type: "error", text: "Failed to add campaign." });
        }
      },
      W = (F) => {
        L(F.campaign_id),
          p({
            description: F.description,
            mail_subject: F.mail_subject,
            mail_body: F.mail_body,
            attachment: null,
          }),
          R(null),
          b(!0);
      },
      re = async (F) => {
        F.preventDefault();
        const oe = new FormData();
        oe.append("description", y.description),
          oe.append("mail_subject", y.mail_subject),
          oe.append("mail_body", y.mail_body),
          v && oe.append("attachment", v),
          oe.append("_method", "PUT");
        try {
          const S = await (
            await fetch(`${Z}?id=${N}`, { method: "POST", body: oe })
          ).json();
          S.success
            ? (C({ type: "success", text: "Campaign updated successfully!" }),
              b(!1),
              p(yi),
              R(null),
              U())
            : C({
                type: "error",
                text: S.message || "Failed to update campaign.",
              });
        } catch {
          C({ type: "error", text: "Failed to update campaign." });
        }
      },
      ue = async (F) => {
        u((oe) => oe.filter((pe) => pe.campaign_id !== F)),
          Y((oe) => ({ ...oe, total: oe.total - 1 }));
        try {
          const pe = await (
            await fetch(`${Z}?id=${F}`, { method: "DELETE" })
          ).json();
          pe.success
            ? C({ type: "success", text: "Campaign deleted successfully!" })
            : (C({
                type: "error",
                text: pe.message || "Failed to delete campaign.",
              }),
              U());
        } catch {
          C({ type: "error", text: "Failed to delete campaign." }), U();
        }
      },
      ce = async (F) => {
        try {
          const pe = await (await fetch(`${Z}?id=${F}`)).json();
          p({
            description: pe.description,
            mail_subject: pe.mail_subject,
            mail_body: pe.mail_body,
            attachment: null,
          }),
            L(null),
            R(null),
            m(!0);
        } catch {
          C({ type: "error", text: "Failed to load campaign for reuse." });
        }
      };
    T.useEffect(() => {
      if (w) {
        const F = setTimeout(() => C(null), 3e3);
        return () => clearTimeout(F);
      }
    }, [w]);
    const je = (F) => {
        const oe = F.split(/\s+/);
        return oe.slice(0, 30).join(" ") + (oe.length > 30 ? "..." : "");
      },
      Ue = Math.max(1, Math.ceil(D.total / D.rowsPerPage)),
      Me = s.slice((D.page - 1) * D.rowsPerPage, D.page * D.rowsPerPage);
    return r.jsxs("div", {
      className: "container mx-auto mt-12 px-2 sm:px-4 py-8 max-w-7xl",
      children: [
        r.jsx(Jg, { message: w, onClose: () => C(null) }),
        r.jsxs("div", {
          className: "flex justify-between items-center mb-6",
          children: [
            r.jsxs("h1", {
              className: "text-2xl font-bold text-gray-800",
              children: [
                r.jsx("i", { className: "fas fa-bullhorn mr-2 text-blue-600" }),
                "Email Campaigns",
              ],
            }),
            r.jsxs("button", {
              onClick: () => {
                p(yi), m(!0);
              },
              className:
                "bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center",
              children: [
                r.jsx("i", { className: "fas fa-plus mr-2" }),
                " Add Campaign",
              ],
            }),
          ],
        }),
        r.jsx("div", {
          className: "block sm:hidden",
          children: Me.map((F) =>
            r.jsxs(
              "div",
              {
                className: "bg-white rounded-2xl shadow p-4 mb-4 flex flex-col",
                children: [
                  r.jsxs("div", {
                    className: "flex justify-between items-start",
                    children: [
                      r.jsxs("div", {
                        children: [
                          r.jsxs("div", {
                            className: "text-lg font-bold text-gray-900 mb-1",
                            children: ["ID: ", F.campaign_id],
                          }),
                          r.jsx("div", {
                            className: "font-semibold text-gray-900",
                            children: "Description:",
                          }),
                          r.jsx("div", {
                            className:
                              "text-gray-600 text-base mb-2 break-words",
                            children: F.description,
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        className: "flex flex-col gap-2 items-end ml-2",
                        children: [
                          r.jsx("button", {
                            onClick: () => W(F),
                            className:
                              "text-blue-600 hover:text-blue-800 p-1 rounded",
                            title: "Edit",
                            children: r.jsx("i", {
                              className: "fas fa-edit text-xl",
                            }),
                          }),
                          r.jsx("button", {
                            onClick: () => ce(F.campaign_id),
                            className:
                              "text-green-600 hover:text-green-800 p-1 rounded",
                            title: "Reuse",
                            children: r.jsx("i", {
                              className: "fas fa-copy text-xl",
                            }),
                          }),
                          r.jsx("button", {
                            onClick: () => {
                              window.confirm(
                                "Are you sure you want to delete this campaign?"
                              ) && ue(F.campaign_id);
                            },
                            className:
                              "text-red-600 hover:text-red-800 p-1 rounded",
                            title: "Delete",
                            children: r.jsx("i", {
                              className: "fas fa-trash text-xl",
                            }),
                          }),
                        ],
                      }),
                    ],
                  }),
                  r.jsxs("div", {
                    className: "mt-2",
                    children: [
                      r.jsx("div", {
                        className: "font-semibold text-gray-900",
                        children: "Subject:",
                      }),
                      r.jsx("div", {
                        className: "text-gray-700 text-sm break-words mb-1",
                        children: F.mail_subject,
                      }),
                      r.jsx("div", {
                        className: "font-semibold text-gray-900",
                        children: "Email Preview:",
                      }),
                      r.jsx("div", {
                        className: "text-gray-500 text-sm break-words",
                        children: je(F.mail_body),
                      }),
                    ],
                  }),
                ],
              },
              F.campaign_id
            )
          ),
        }),
        r.jsx("div", {
          className:
            "hidden sm:block bg-white rounded-lg shadow overflow-hidden",
          children: r.jsx("div", {
            className: "overflow-x-auto",
            children: r.jsxs("table", {
              className: "min-w-full divide-y divide-gray-200",
              children: [
                r.jsx("thead", {
                  className: "bg-gray-50",
                  children: r.jsxs("tr", {
                    children: [
                      r.jsx("th", {
                        className:
                          "w-16 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "ID",
                      }),
                      r.jsx("th", {
                        className:
                          "px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "Description",
                      }),
                      r.jsx("th", {
                        className:
                          "px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "Subject",
                      }),
                      r.jsx("th", {
                        className:
                          "px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "Email Preview",
                      }),
                      r.jsx("th", {
                        className:
                          "w-40 px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "Actions",
                      }),
                    ],
                  }),
                }),
                r.jsx("tbody", {
                  className: "bg-white divide-y divide-gray-200",
                  children: o
                    ? r.jsx("tr", {
                        children: r.jsx("td", {
                          colSpan: 5,
                          className:
                            "px-6 py-4 text-center text-sm text-gray-500",
                          children: "Loading...",
                        }),
                      })
                    : s.length === 0
                    ? r.jsx("tr", {
                        children: r.jsx("td", {
                          colSpan: 5,
                          className:
                            "px-6 py-4 text-center text-sm text-gray-500",
                          children:
                            "No campaigns found. Add one to get started.",
                        }),
                      })
                    : Me.map((F) =>
                        r.jsxs(
                          "tr",
                          {
                            className:
                              "hover:bg-gray-50 transition-colors duration-150",
                            children: [
                              r.jsx("td", {
                                className:
                                  "px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-500",
                                children: F.campaign_id,
                              }),
                              r.jsx("td", {
                                className: "px-4 py-3",
                                children: r.jsx("div", {
                                  className:
                                    "text-sm font-medium text-gray-900 truncate max-w-xs",
                                  children: F.description,
                                }),
                              }),
                              r.jsx("td", {
                                className: "px-4 py-3",
                                children: r.jsx("div", {
                                  className:
                                    "text-sm text-gray-900 truncate max-w-xs",
                                  children: F.mail_subject,
                                }),
                              }),
                              r.jsx("td", {
                                className: "px-4 py-3",
                                children: r.jsx("div", {
                                  className:
                                    "text-sm text-gray-500 truncate max-w-xs",
                                  title: je(F.mail_body),
                                  children: je(F.mail_body),
                                }),
                              }),
                              r.jsx("td", {
                                className:
                                  "px-4 py-3 whitespace-nowrap text-right text-sm font-medium",
                                children: r.jsxs("div", {
                                  className: "flex justify-end space-x-2",
                                  children: [
                                    r.jsx("button", {
                                      onClick: () => W(F),
                                      className:
                                        "text-blue-600 hover:text-blue-800 p-1 rounded hover:bg-blue-50",
                                      title: "Edit",
                                      children: r.jsx("i", {
                                        className: "fas fa-edit",
                                      }),
                                    }),
                                    r.jsx("button", {
                                      onClick: () => ce(F.campaign_id),
                                      className:
                                        "text-green-600 hover:text-green-800 p-1 rounded hover:bg-green-50",
                                      title: "Reuse",
                                      children: r.jsx("i", {
                                        className: "fas fa-copy",
                                      }),
                                    }),
                                    r.jsx("button", {
                                      onClick: () => {
                                        window.confirm(
                                          "Are you sure you want to delete this campaign?"
                                        ) && ue(F.campaign_id);
                                      },
                                      className:
                                        "text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50",
                                      title: "Delete",
                                      children: r.jsx("i", {
                                        className: "fas fa-trash",
                                      }),
                                    }),
                                  ],
                                }),
                              }),
                            ],
                          },
                          F.campaign_id
                        )
                      ),
                }),
              ],
            }),
          }),
        }),
        s.length > 0 &&
          r.jsxs("div", {
            className:
              "flex flex-col items-center justify-center mt-6 px-1 gap-2",
            children: [
              r.jsxs("div", {
                className: "text-xs sm:text-sm text-gray-500 mb-2",
                children: [
                  "Showing",
                  " ",
                  r.jsx("span", {
                    className: "font-medium",
                    children: (D.page - 1) * D.rowsPerPage + 1,
                  }),
                  " ",
                  "to",
                  " ",
                  r.jsx("span", {
                    className: "font-medium",
                    children: Math.min(D.page * D.rowsPerPage, D.total),
                  }),
                  " ",
                  "of ",
                  r.jsx("span", {
                    className: "font-medium",
                    children: D.total,
                  }),
                  " ",
                  "campaigns",
                ],
              }),
              r.jsxs("div", {
                className: "flex flex-wrap items-center gap-2 pb-5",
                children: [
                  r.jsx("button", {
                    onClick: () => Y((F) => ({ ...F, page: 1 })),
                    disabled: D.page === 1,
                    className:
                      "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors",
                    children: r.jsx("svg", {
                      className: "w-5 h-5 text-gray-500",
                      fill: "none",
                      stroke: "currentColor",
                      viewBox: "0 0 24 24",
                      children: r.jsx("path", {
                        strokeLinecap: "round",
                        strokeLinejoin: "round",
                        strokeWidth: "2",
                        d: "M11 19l-7-7 7-7m8 14l-7-7 7-7",
                      }),
                    }),
                  }),
                  r.jsx("button", {
                    onClick: () => Y((F) => ({ ...F, page: F.page - 1 })),
                    disabled: D.page === 1,
                    className:
                      "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors",
                    children: r.jsx("svg", {
                      className: "w-5 h-5 text-gray-500",
                      fill: "none",
                      stroke: "currentColor",
                      viewBox: "0 0 24 24",
                      children: r.jsx("path", {
                        strokeLinecap: "round",
                        strokeLinejoin: "round",
                        strokeWidth: "2",
                        d: "M15 19l-7-7 7-7",
                      }),
                    }),
                  }),
                  r.jsxs("span", {
                    className: "text-xs sm:text-sm font-medium text-gray-700",
                    children: ["Page ", D.page, " of ", Ue],
                  }),
                  r.jsx("button", {
                    onClick: () =>
                      Y((F) => ({ ...F, page: Math.min(Ue, F.page + 1) })),
                    disabled: D.page >= Ue,
                    className:
                      "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors",
                    children: r.jsx("svg", {
                      className: "w-5 h-5 text-gray-500",
                      fill: "none",
                      stroke: "currentColor",
                      viewBox: "0 0 24 24",
                      children: r.jsx("path", {
                        strokeLinecap: "round",
                        strokeLinejoin: "round",
                        strokeWidth: "2",
                        d: "M9 5l7 7-7 7",
                      }),
                    }),
                  }),
                  r.jsx("button", {
                    onClick: () => Y((F) => ({ ...F, page: Ue })),
                    disabled: D.page >= Ue,
                    className:
                      "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors",
                    children: r.jsx("svg", {
                      className: "w-5 h-5 text-gray-500",
                      fill: "none",
                      stroke: "currentColor",
                      viewBox: "0 0 24 24",
                      children: r.jsx("path", {
                        strokeLinecap: "round",
                        strokeLinejoin: "round",
                        strokeWidth: "2",
                        d: "M13 5l7 7-7 7M5 5l7 7-7 7",
                      }),
                    }),
                  }),
                  r.jsx("select", {
                    value: D.rowsPerPage,
                    onChange: (F) =>
                      Y((oe) => ({
                        ...oe,
                        rowsPerPage: Number(F.target.value),
                        page: 1,
                      })),
                    className:
                      "border p-2 rounded-lg text-xs sm:text-sm bg-white focus:ring-blue-500 focus:border-blue-500 transition-colors",
                    children: [10, 25, 50, 100].map((F) =>
                      r.jsx("option", { value: F, children: F }, F)
                    ),
                  }),
                ],
              }),
            ],
          }),
        f &&
          r.jsx("div", {
            className:
              "fixed inset-0 bg-gr bg-black/30 backdrop-blur-md backdrop-saturate-150 border border-white/20 shadow-xl overflow-y-auto h-full w-full z-50 flex items-center justify-center",
            children: r.jsxs("div", {
              className:
                "relative mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white",
              children: [
                r.jsxs("div", {
                  className: "flex justify-between items-center mb-4",
                  children: [
                    r.jsxs("h3", {
                      className: "text-lg font-medium text-gray-900",
                      children: [
                        r.jsx("i", {
                          className: "fas fa-plus-circle mr-2 text-blue-600",
                        }),
                        "Add New Campaign",
                      ],
                    }),
                    r.jsx("button", {
                      onClick: () => m(!1),
                      className: "text-gray-400 hover:text-gray-500",
                      children: r.jsx("i", { className: "fas fa-times" }),
                    }),
                  ],
                }),
                r.jsxs("form", {
                  className: "space-y-4",
                  onSubmit: Q,
                  encType: "multipart/form-data",
                  children: [
                    r.jsxs("div", {
                      children: [
                        r.jsx("label", {
                          className:
                            "block text-sm font-medium text-gray-700 mb-1",
                          children: "Description",
                        }),
                        r.jsx("input", {
                          type: "text",
                          name: "description",
                          required: !0,
                          className:
                            "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm",
                          placeholder: "Campaign description",
                          value: y.description,
                          onChange: q,
                        }),
                      ],
                    }),
                    r.jsxs("div", {
                      children: [
                        r.jsx("label", {
                          className:
                            "block text-sm font-medium text-gray-700 mb-1",
                          children: "Email Subject",
                        }),
                        r.jsx("input", {
                          type: "text",
                          name: "mail_subject",
                          required: !0,
                          className:
                            "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm",
                          placeholder: "Your email subject",
                          value: y.mail_subject,
                          onChange: q,
                        }),
                      ],
                    }),
                    r.jsxs("div", {
                      children: [
                        r.jsx("label", {
                          className:
                            "block text-sm font-medium text-gray-700 mb-1",
                          children: "Email Body",
                        }),
                        r.jsx("textarea", {
                          name: "mail_body",
                          rows: 8,
                          required: !0,
                          className:
                            "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm font-mono text-sm",
                          placeholder: "Compose your email content here...",
                          value: y.mail_body,
                          onChange: q,
                        }),
                      ],
                    }),
                    r.jsxs("div", {
                      children: [
                        r.jsx("label", {
                          className:
                            "block text-sm font-medium text-black-700 mb-1",
                          children: "Attachment",
                        }),
                        r.jsx("input", {
                          type: "file",
                          name: "attachment",
                          className:
                            "block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100",
                          onChange: q,
                          accept:
                            ".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.txt",
                          id: "attachment-input",
                        }),
                        r.jsx("div", {
                          className: "text-xs text-gray-500 mt-1",
                          children: v
                            ? `Selected: ${v.name}`
                            : "No file chosen",
                        }),
                      ],
                    }),
                    r.jsxs("div", {
                      className: "flex justify-end pt-4 space-x-3",
                      children: [
                        r.jsx("button", {
                          type: "button",
                          onClick: () => m(!1),
                          className:
                            "bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500",
                          children: "Cancel",
                        }),
                        r.jsxs("button", {
                          type: "submit",
                          className:
                            "bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md shadow-sm text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500",
                          children: [
                            r.jsx("i", { className: "fas fa-save mr-2" }),
                            " Save Campaign",
                          ],
                        }),
                      ],
                    }),
                  ],
                }),
              ],
            }),
          }),
        x &&
          r.jsx("div", {
            className:
              "fixed inset-0  bg-black/30 backdrop-blur-md backdrop-saturate-150 overflow-y-auto h-full w-full z-50 flex items-center justify-center",
            children: r.jsxs("div", {
              className:
                "relative mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white",
              children: [
                r.jsxs("div", {
                  className: "flex justify-between items-center mb-4",
                  children: [
                    r.jsxs("h3", {
                      className: "text-lg font-medium text-gray-900",
                      children: [
                        r.jsx("i", {
                          className: "fas fa-edit mr-2 text-blue-600",
                        }),
                        "Edit Campaign",
                      ],
                    }),
                    r.jsx("button", {
                      onClick: () => b(!1),
                      className: "text-gray-400 hover:text-gray-500",
                      children: r.jsx("i", { className: "fas fa-times" }),
                    }),
                  ],
                }),
                r.jsxs("form", {
                  className: "space-y-4",
                  onSubmit: re,
                  encType: "multipart/form-data",
                  children: [
                    r.jsxs("div", {
                      children: [
                        r.jsx("label", {
                          className:
                            "block text-sm font-medium text-gray-700 mb-1",
                          children: "Description",
                        }),
                        r.jsx("input", {
                          type: "text",
                          name: "description",
                          required: !0,
                          className:
                            "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm",
                          value: y.description,
                          onChange: q,
                        }),
                      ],
                    }),
                    r.jsxs("div", {
                      children: [
                        r.jsx("label", {
                          className:
                            "block text-sm font-medium text-gray-700 mb-1",
                          children: "Email Subject",
                        }),
                        r.jsx("input", {
                          type: "text",
                          name: "mail_subject",
                          required: !0,
                          className:
                            "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm",
                          value: y.mail_subject,
                          onChange: q,
                        }),
                      ],
                    }),
                    r.jsxs("div", {
                      children: [
                        r.jsx("label", {
                          className:
                            "block text-sm font-medium text-gray-700 mb-1",
                          children: "Email Body",
                        }),
                        r.jsx("textarea", {
                          name: "mail_body",
                          rows: 8,
                          required: !0,
                          className:
                            "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm font-mono text-sm",
                          value: y.mail_body,
                          onChange: q,
                        }),
                      ],
                    }),
                    r.jsxs("div", {
                      children: [
                        r.jsx("label", {
                          className:
                            "block text-sm font-medium text-gray-700 mb-1",
                          children: "Attachment",
                        }),
                        r.jsx("input", {
                          type: "file",
                          name: "attachment",
                          className:
                            "block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100",
                          onChange: q,
                          accept: "*",
                        }),
                        v &&
                          r.jsxs("div", {
                            className: "text-xs text-gray-500 mt-1",
                            children: ["Selected: ", v.name],
                          }),
                      ],
                    }),
                    r.jsxs("div", {
                      className: "flex justify-end pt-4 space-x-3",
                      children: [
                        r.jsx("button", {
                          type: "button",
                          onClick: () => b(!1),
                          className:
                            "bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500",
                          children: "Cancel",
                        }),
                        r.jsxs("button", {
                          type: "submit",
                          className:
                            "bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md shadow-sm text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500",
                          children: [
                            r.jsx("i", { className: "fas fa-save mr-2" }),
                            " Update",
                          ],
                        }),
                      ],
                    }),
                  ],
                }),
              ],
            }),
          }),
      ],
    });
  };
function gh(s, u) {
  return function () {
    return s.apply(u, arguments);
  };
}
const { toString: Fg } = Object.prototype,
  { getPrototypeOf: Ec } = Object,
  { iterator: Oi, toStringTag: yh } = Symbol,
  Mi = ((s) => (u) => {
    const o = Fg.call(u);
    return s[o] || (s[o] = o.slice(8, -1).toLowerCase());
  })(Object.create(null)),
  Mt = (s) => ((s = s.toLowerCase()), (u) => Mi(u) === s),
  Di = (s) => (u) => typeof u === s,
  { isArray: Yl } = Array,
  Jn = Di("undefined");
function Wg(s) {
  return (
    s !== null &&
    !Jn(s) &&
    s.constructor !== null &&
    !Jn(s.constructor) &&
    ut(s.constructor.isBuffer) &&
    s.constructor.isBuffer(s)
  );
}
const bh = Mt("ArrayBuffer");
function Pg(s) {
  let u;
  return (
    typeof ArrayBuffer < "u" && ArrayBuffer.isView
      ? (u = ArrayBuffer.isView(s))
      : (u = s && s.buffer && bh(s.buffer)),
    u
  );
}
const Ig = Di("string"),
  ut = Di("function"),
  vh = Di("number"),
  zi = (s) => s !== null && typeof s == "object",
  ey = (s) => s === !0 || s === !1,
  Ni = (s) => {
    if (Mi(s) !== "object") return !1;
    const u = Ec(s);
    return (
      (u === null ||
        u === Object.prototype ||
        Object.getPrototypeOf(u) === null) &&
      !(yh in s) &&
      !(Oi in s)
    );
  },
  ty = Mt("Date"),
  ay = Mt("File"),
  ly = Mt("Blob"),
  ny = Mt("FileList"),
  sy = (s) => zi(s) && ut(s.pipe),
  iy = (s) => {
    let u;
    return (
      s &&
      ((typeof FormData == "function" && s instanceof FormData) ||
        (ut(s.append) &&
          ((u = Mi(s)) === "formdata" ||
            (u === "object" &&
              ut(s.toString) &&
              s.toString() === "[object FormData]"))))
    );
  },
  ry = Mt("URLSearchParams"),
  [uy, cy, oy, fy] = ["ReadableStream", "Request", "Response", "Headers"].map(
    Mt
  ),
  dy = (s) =>
    s.trim ? s.trim() : s.replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, "");
function Pn(s, u, { allOwnKeys: o = !1 } = {}) {
  if (s === null || typeof s > "u") return;
  let c, f;
  if ((typeof s != "object" && (s = [s]), Yl(s)))
    for (c = 0, f = s.length; c < f; c++) u.call(null, s[c], c, s);
  else {
    const m = o ? Object.getOwnPropertyNames(s) : Object.keys(s),
      x = m.length;
    let b;
    for (c = 0; c < x; c++) (b = m[c]), u.call(null, s[b], b, s);
  }
}
function jh(s, u) {
  u = u.toLowerCase();
  const o = Object.keys(s);
  let c = o.length,
    f;
  for (; c-- > 0; ) if (((f = o[c]), u === f.toLowerCase())) return f;
  return null;
}
const Fa =
    typeof globalThis < "u"
      ? globalThis
      : typeof self < "u"
      ? self
      : typeof window < "u"
      ? window
      : global,
  Sh = (s) => !Jn(s) && s !== Fa;
function mc() {
  const { caseless: s } = (Sh(this) && this) || {},
    u = {},
    o = (c, f) => {
      const m = (s && jh(u, f)) || f;
      Ni(u[m]) && Ni(c)
        ? (u[m] = mc(u[m], c))
        : Ni(c)
        ? (u[m] = mc({}, c))
        : Yl(c)
        ? (u[m] = c.slice())
        : (u[m] = c);
    };
  for (let c = 0, f = arguments.length; c < f; c++)
    arguments[c] && Pn(arguments[c], o);
  return u;
}
const my = (s, u, o, { allOwnKeys: c } = {}) => (
    Pn(
      u,
      (f, m) => {
        o && ut(f) ? (s[m] = gh(f, o)) : (s[m] = f);
      },
      { allOwnKeys: c }
    ),
    s
  ),
  hy = (s) => (s.charCodeAt(0) === 65279 && (s = s.slice(1)), s),
  py = (s, u, o, c) => {
    (s.prototype = Object.create(u.prototype, c)),
      (s.prototype.constructor = s),
      Object.defineProperty(s, "super", { value: u.prototype }),
      o && Object.assign(s.prototype, o);
  },
  xy = (s, u, o, c) => {
    let f, m, x;
    const b = {};
    if (((u = u || {}), s == null)) return u;
    do {
      for (f = Object.getOwnPropertyNames(s), m = f.length; m-- > 0; )
        (x = f[m]), (!c || c(x, s, u)) && !b[x] && ((u[x] = s[x]), (b[x] = !0));
      s = o !== !1 && Ec(s);
    } while (s && (!o || o(s, u)) && s !== Object.prototype);
    return u;
  },
  gy = (s, u, o) => {
    (s = String(s)),
      (o === void 0 || o > s.length) && (o = s.length),
      (o -= u.length);
    const c = s.indexOf(u, o);
    return c !== -1 && c === o;
  },
  yy = (s) => {
    if (!s) return null;
    if (Yl(s)) return s;
    let u = s.length;
    if (!vh(u)) return null;
    const o = new Array(u);
    for (; u-- > 0; ) o[u] = s[u];
    return o;
  },
  by = (
    (s) => (u) =>
      s && u instanceof s
  )(typeof Uint8Array < "u" && Ec(Uint8Array)),
  vy = (s, u) => {
    const c = (s && s[Oi]).call(s);
    let f;
    for (; (f = c.next()) && !f.done; ) {
      const m = f.value;
      u.call(s, m[0], m[1]);
    }
  },
  jy = (s, u) => {
    let o;
    const c = [];
    for (; (o = s.exec(u)) !== null; ) c.push(o);
    return c;
  },
  Sy = Mt("HTMLFormElement"),
  Ny = (s) =>
    s.toLowerCase().replace(/[-_\s]([a-z\d])(\w*)/g, function (o, c, f) {
      return c.toUpperCase() + f;
    }),
  Bm = (
    ({ hasOwnProperty: s }) =>
    (u, o) =>
      s.call(u, o)
  )(Object.prototype),
  wy = Mt("RegExp"),
  Nh = (s, u) => {
    const o = Object.getOwnPropertyDescriptors(s),
      c = {};
    Pn(o, (f, m) => {
      let x;
      (x = u(f, m, s)) !== !1 && (c[m] = x || f);
    }),
      Object.defineProperties(s, c);
  },
  Ey = (s) => {
    Nh(s, (u, o) => {
      if (ut(s) && ["arguments", "caller", "callee"].indexOf(o) !== -1)
        return !1;
      const c = s[o];
      if (ut(c)) {
        if (((u.enumerable = !1), "writable" in u)) {
          u.writable = !1;
          return;
        }
        u.set ||
          (u.set = () => {
            throw Error("Can not rewrite read-only method '" + o + "'");
          });
      }
    });
  },
  Ty = (s, u) => {
    const o = {},
      c = (f) => {
        f.forEach((m) => {
          o[m] = !0;
        });
      };
    return Yl(s) ? c(s) : c(String(s).split(u)), o;
  },
  _y = () => {},
  Ay = (s, u) => (s != null && Number.isFinite((s = +s)) ? s : u);
function Ry(s) {
  return !!(s && ut(s.append) && s[yh] === "FormData" && s[Oi]);
}
const Cy = (s) => {
    const u = new Array(10),
      o = (c, f) => {
        if (zi(c)) {
          if (u.indexOf(c) >= 0) return;
          if (!("toJSON" in c)) {
            u[f] = c;
            const m = Yl(c) ? [] : {};
            return (
              Pn(c, (x, b) => {
                const y = o(x, f + 1);
                !Jn(y) && (m[b] = y);
              }),
              (u[f] = void 0),
              m
            );
          }
        }
        return c;
      };
    return o(s, 0);
  },
  Oy = Mt("AsyncFunction"),
  My = (s) => s && (zi(s) || ut(s)) && ut(s.then) && ut(s.catch),
  wh = ((s, u) =>
    s
      ? setImmediate
      : u
      ? ((o, c) => (
          Fa.addEventListener(
            "message",
            ({ source: f, data: m }) => {
              f === Fa && m === o && c.length && c.shift()();
            },
            !1
          ),
          (f) => {
            c.push(f), Fa.postMessage(o, "*");
          }
        ))(`axios@${Math.random()}`, [])
      : (o) => setTimeout(o))(
    typeof setImmediate == "function",
    ut(Fa.postMessage)
  ),
  Dy =
    typeof queueMicrotask < "u"
      ? queueMicrotask.bind(Fa)
      : (typeof process < "u" && process.nextTick) || wh,
  zy = (s) => s != null && ut(s[Oi]),
  B = {
    isArray: Yl,
    isArrayBuffer: bh,
    isBuffer: Wg,
    isFormData: iy,
    isArrayBufferView: Pg,
    isString: Ig,
    isNumber: vh,
    isBoolean: ey,
    isObject: zi,
    isPlainObject: Ni,
    isReadableStream: uy,
    isRequest: cy,
    isResponse: oy,
    isHeaders: fy,
    isUndefined: Jn,
    isDate: ty,
    isFile: ay,
    isBlob: ly,
    isRegExp: wy,
    isFunction: ut,
    isStream: sy,
    isURLSearchParams: ry,
    isTypedArray: by,
    isFileList: ny,
    forEach: Pn,
    merge: mc,
    extend: my,
    trim: dy,
    stripBOM: hy,
    inherits: py,
    toFlatObject: xy,
    kindOf: Mi,
    kindOfTest: Mt,
    endsWith: gy,
    toArray: yy,
    forEachEntry: vy,
    matchAll: jy,
    isHTMLForm: Sy,
    hasOwnProperty: Bm,
    hasOwnProp: Bm,
    reduceDescriptors: Nh,
    freezeMethods: Ey,
    toObjectSet: Ty,
    toCamelCase: Ny,
    noop: _y,
    toFiniteNumber: Ay,
    findKey: jh,
    global: Fa,
    isContextDefined: Sh,
    isSpecCompliantForm: Ry,
    toJSONObject: Cy,
    isAsyncFn: Oy,
    isThenable: My,
    setImmediate: wh,
    asap: Dy,
    isIterable: zy,
  };
function fe(s, u, o, c, f) {
  Error.call(this),
    Error.captureStackTrace
      ? Error.captureStackTrace(this, this.constructor)
      : (this.stack = new Error().stack),
    (this.message = s),
    (this.name = "AxiosError"),
    u && (this.code = u),
    o && (this.config = o),
    c && (this.request = c),
    f && ((this.response = f), (this.status = f.status ? f.status : null));
}
B.inherits(fe, Error, {
  toJSON: function () {
    return {
      message: this.message,
      name: this.name,
      description: this.description,
      number: this.number,
      fileName: this.fileName,
      lineNumber: this.lineNumber,
      columnNumber: this.columnNumber,
      stack: this.stack,
      config: B.toJSONObject(this.config),
      code: this.code,
      status: this.status,
    };
  },
});
const Eh = fe.prototype,
  Th = {};
[
  "ERR_BAD_OPTION_VALUE",
  "ERR_BAD_OPTION",
  "ECONNABORTED",
  "ETIMEDOUT",
  "ERR_NETWORK",
  "ERR_FR_TOO_MANY_REDIRECTS",
  "ERR_DEPRECATED",
  "ERR_BAD_RESPONSE",
  "ERR_BAD_REQUEST",
  "ERR_CANCELED",
  "ERR_NOT_SUPPORT",
  "ERR_INVALID_URL",
].forEach((s) => {
  Th[s] = { value: s };
});
Object.defineProperties(fe, Th);
Object.defineProperty(Eh, "isAxiosError", { value: !0 });
fe.from = (s, u, o, c, f, m) => {
  const x = Object.create(Eh);
  return (
    B.toFlatObject(
      s,
      x,
      function (y) {
        return y !== Error.prototype;
      },
      (b) => b !== "isAxiosError"
    ),
    fe.call(x, s.message, u, o, c, f),
    (x.cause = s),
    (x.name = s.name),
    m && Object.assign(x, m),
    x
  );
};
const Uy = null;
function hc(s) {
  return B.isPlainObject(s) || B.isArray(s);
}
function _h(s) {
  return B.endsWith(s, "[]") ? s.slice(0, -2) : s;
}
function Hm(s, u, o) {
  return s
    ? s
        .concat(u)
        .map(function (f, m) {
          return (f = _h(f)), !o && m ? "[" + f + "]" : f;
        })
        .join(o ? "." : "")
    : u;
}
function ky(s) {
  return B.isArray(s) && !s.some(hc);
}
const Ly = B.toFlatObject(B, {}, null, function (u) {
  return /^is[A-Z]/.test(u);
});
function Ui(s, u, o) {
  if (!B.isObject(s)) throw new TypeError("target must be an object");
  (u = u || new FormData()),
    (o = B.toFlatObject(
      o,
      { metaTokens: !0, dots: !1, indexes: !1 },
      !1,
      function (C, D) {
        return !B.isUndefined(D[C]);
      }
    ));
  const c = o.metaTokens,
    f = o.visitor || v,
    m = o.dots,
    x = o.indexes,
    y = (o.Blob || (typeof Blob < "u" && Blob)) && B.isSpecCompliantForm(u);
  if (!B.isFunction(f)) throw new TypeError("visitor must be a function");
  function p(w) {
    if (w === null) return "";
    if (B.isDate(w)) return w.toISOString();
    if (B.isBoolean(w)) return w.toString();
    if (!y && B.isBlob(w))
      throw new fe("Blob is not supported. Use a Buffer instead.");
    return B.isArrayBuffer(w) || B.isTypedArray(w)
      ? y && typeof Blob == "function"
        ? new Blob([w])
        : Buffer.from(w)
      : w;
  }
  function v(w, C, D) {
    let Y = w;
    if (w && !D && typeof w == "object") {
      if (B.endsWith(C, "{}"))
        (C = c ? C : C.slice(0, -2)), (w = JSON.stringify(w));
      else if (
        (B.isArray(w) && ky(w)) ||
        ((B.isFileList(w) || B.endsWith(C, "[]")) && (Y = B.toArray(w)))
      )
        return (
          (C = _h(C)),
          Y.forEach(function (U, q) {
            !(B.isUndefined(U) || U === null) &&
              u.append(
                x === !0 ? Hm([C], q, m) : x === null ? C : C + "[]",
                p(U)
              );
          }),
          !1
        );
    }
    return hc(w) ? !0 : (u.append(Hm(D, C, m), p(w)), !1);
  }
  const R = [],
    N = Object.assign(Ly, {
      defaultVisitor: v,
      convertValue: p,
      isVisitable: hc,
    });
  function L(w, C) {
    if (!B.isUndefined(w)) {
      if (R.indexOf(w) !== -1)
        throw Error("Circular reference detected in " + C.join("."));
      R.push(w),
        B.forEach(w, function (Y, Z) {
          (!(B.isUndefined(Y) || Y === null) &&
            f.call(u, Y, B.isString(Z) ? Z.trim() : Z, C, N)) === !0 &&
            L(Y, C ? C.concat(Z) : [Z]);
        }),
        R.pop();
    }
  }
  if (!B.isObject(s)) throw new TypeError("data must be an object");
  return L(s), u;
}
function qm(s) {
  const u = {
    "!": "%21",
    "'": "%27",
    "(": "%28",
    ")": "%29",
    "~": "%7E",
    "%20": "+",
    "%00": "\0",
  };
  return encodeURIComponent(s).replace(/[!'()~]|%20|%00/g, function (c) {
    return u[c];
  });
}
function Tc(s, u) {
  (this._pairs = []), s && Ui(s, this, u);
}
const Ah = Tc.prototype;
Ah.append = function (u, o) {
  this._pairs.push([u, o]);
};
Ah.toString = function (u) {
  const o = u
    ? function (c) {
        return u.call(this, c, qm);
      }
    : qm;
  return this._pairs
    .map(function (f) {
      return o(f[0]) + "=" + o(f[1]);
    }, "")
    .join("&");
};
function By(s) {
  return encodeURIComponent(s)
    .replace(/%3A/gi, ":")
    .replace(/%24/g, "$")
    .replace(/%2C/gi, ",")
    .replace(/%20/g, "+")
    .replace(/%5B/gi, "[")
    .replace(/%5D/gi, "]");
}
function Rh(s, u, o) {
  if (!u) return s;
  const c = (o && o.encode) || By;
  B.isFunction(o) && (o = { serialize: o });
  const f = o && o.serialize;
  let m;
  if (
    (f
      ? (m = f(u, o))
      : (m = B.isURLSearchParams(u) ? u.toString() : new Tc(u, o).toString(c)),
    m)
  ) {
    const x = s.indexOf("#");
    x !== -1 && (s = s.slice(0, x)),
      (s += (s.indexOf("?") === -1 ? "?" : "&") + m);
  }
  return s;
}
class Ym {
  constructor() {
    this.handlers = [];
  }
  use(u, o, c) {
    return (
      this.handlers.push({
        fulfilled: u,
        rejected: o,
        synchronous: c ? c.synchronous : !1,
        runWhen: c ? c.runWhen : null,
      }),
      this.handlers.length - 1
    );
  }
  eject(u) {
    this.handlers[u] && (this.handlers[u] = null);
  }
  clear() {
    this.handlers && (this.handlers = []);
  }
  forEach(u) {
    B.forEach(this.handlers, function (c) {
      c !== null && u(c);
    });
  }
}
const Ch = {
    silentJSONParsing: !0,
    forcedJSONParsing: !0,
    clarifyTimeoutError: !1,
  },
  Hy = typeof URLSearchParams < "u" ? URLSearchParams : Tc,
  qy = typeof FormData < "u" ? FormData : null,
  Yy = typeof Blob < "u" ? Blob : null,
  Vy = {
    isBrowser: !0,
    classes: { URLSearchParams: Hy, FormData: qy, Blob: Yy },
    protocols: ["http", "https", "file", "blob", "url", "data"],
  },
  _c = typeof window < "u" && typeof document < "u",
  pc = (typeof navigator == "object" && navigator) || void 0,
  Gy =
    _c &&
    (!pc || ["ReactNative", "NativeScript", "NS"].indexOf(pc.product) < 0),
  Xy =
    typeof WorkerGlobalScope < "u" &&
    self instanceof WorkerGlobalScope &&
    typeof self.importScripts == "function",
  Qy = (_c && window.location.href) || "http://localhost",
  Zy = Object.freeze(
    Object.defineProperty(
      {
        __proto__: null,
        hasBrowserEnv: _c,
        hasStandardBrowserEnv: Gy,
        hasStandardBrowserWebWorkerEnv: Xy,
        navigator: pc,
        origin: Qy,
      },
      Symbol.toStringTag,
      { value: "Module" }
    )
  ),
  et = { ...Zy, ...Vy };
function Ky(s, u) {
  return Ui(
    s,
    new et.classes.URLSearchParams(),
    Object.assign(
      {
        visitor: function (o, c, f, m) {
          return et.isNode && B.isBuffer(o)
            ? (this.append(c, o.toString("base64")), !1)
            : m.defaultVisitor.apply(this, arguments);
        },
      },
      u
    )
  );
}
function Jy(s) {
  return B.matchAll(/\w+|\[(\w*)]/g, s).map((u) =>
    u[0] === "[]" ? "" : u[1] || u[0]
  );
}
function $y(s) {
  const u = {},
    o = Object.keys(s);
  let c;
  const f = o.length;
  let m;
  for (c = 0; c < f; c++) (m = o[c]), (u[m] = s[m]);
  return u;
}
function Oh(s) {
  function u(o, c, f, m) {
    let x = o[m++];
    if (x === "__proto__") return !0;
    const b = Number.isFinite(+x),
      y = m >= o.length;
    return (
      (x = !x && B.isArray(f) ? f.length : x),
      y
        ? (B.hasOwnProp(f, x) ? (f[x] = [f[x], c]) : (f[x] = c), !b)
        : ((!f[x] || !B.isObject(f[x])) && (f[x] = []),
          u(o, c, f[x], m) && B.isArray(f[x]) && (f[x] = $y(f[x])),
          !b)
    );
  }
  if (B.isFormData(s) && B.isFunction(s.entries)) {
    const o = {};
    return (
      B.forEachEntry(s, (c, f) => {
        u(Jy(c), f, o, 0);
      }),
      o
    );
  }
  return null;
}
function Fy(s, u, o) {
  if (B.isString(s))
    try {
      return (u || JSON.parse)(s), B.trim(s);
    } catch (c) {
      if (c.name !== "SyntaxError") throw c;
    }
  return (o || JSON.stringify)(s);
}
const In = {
  transitional: Ch,
  adapter: ["xhr", "http", "fetch"],
  transformRequest: [
    function (u, o) {
      const c = o.getContentType() || "",
        f = c.indexOf("application/json") > -1,
        m = B.isObject(u);
      if ((m && B.isHTMLForm(u) && (u = new FormData(u)), B.isFormData(u)))
        return f ? JSON.stringify(Oh(u)) : u;
      if (
        B.isArrayBuffer(u) ||
        B.isBuffer(u) ||
        B.isStream(u) ||
        B.isFile(u) ||
        B.isBlob(u) ||
        B.isReadableStream(u)
      )
        return u;
      if (B.isArrayBufferView(u)) return u.buffer;
      if (B.isURLSearchParams(u))
        return (
          o.setContentType(
            "application/x-www-form-urlencoded;charset=utf-8",
            !1
          ),
          u.toString()
        );
      let b;
      if (m) {
        if (c.indexOf("application/x-www-form-urlencoded") > -1)
          return Ky(u, this.formSerializer).toString();
        if ((b = B.isFileList(u)) || c.indexOf("multipart/form-data") > -1) {
          const y = this.env && this.env.FormData;
          return Ui(
            b ? { "files[]": u } : u,
            y && new y(),
            this.formSerializer
          );
        }
      }
      return m || f ? (o.setContentType("application/json", !1), Fy(u)) : u;
    },
  ],
  transformResponse: [
    function (u) {
      const o = this.transitional || In.transitional,
        c = o && o.forcedJSONParsing,
        f = this.responseType === "json";
      if (B.isResponse(u) || B.isReadableStream(u)) return u;
      if (u && B.isString(u) && ((c && !this.responseType) || f)) {
        const x = !(o && o.silentJSONParsing) && f;
        try {
          return JSON.parse(u);
        } catch (b) {
          if (x)
            throw b.name === "SyntaxError"
              ? fe.from(b, fe.ERR_BAD_RESPONSE, this, null, this.response)
              : b;
        }
      }
      return u;
    },
  ],
  timeout: 0,
  xsrfCookieName: "XSRF-TOKEN",
  xsrfHeaderName: "X-XSRF-TOKEN",
  maxContentLength: -1,
  maxBodyLength: -1,
  env: { FormData: et.classes.FormData, Blob: et.classes.Blob },
  validateStatus: function (u) {
    return u >= 200 && u < 300;
  },
  headers: {
    common: {
      Accept: "application/json, text/plain, */*",
      "Content-Type": void 0,
    },
  },
};
B.forEach(["delete", "get", "head", "post", "put", "patch"], (s) => {
  In.headers[s] = {};
});
const Wy = B.toObjectSet([
    "age",
    "authorization",
    "content-length",
    "content-type",
    "etag",
    "expires",
    "from",
    "host",
    "if-modified-since",
    "if-unmodified-since",
    "last-modified",
    "location",
    "max-forwards",
    "proxy-authorization",
    "referer",
    "retry-after",
    "user-agent",
  ]),
  Py = (s) => {
    const u = {};
    let o, c, f;
    return (
      s &&
        s
          .split(
            `
`
          )
          .forEach(function (x) {
            (f = x.indexOf(":")),
              (o = x.substring(0, f).trim().toLowerCase()),
              (c = x.substring(f + 1).trim()),
              !(!o || (u[o] && Wy[o])) &&
                (o === "set-cookie"
                  ? u[o]
                    ? u[o].push(c)
                    : (u[o] = [c])
                  : (u[o] = u[o] ? u[o] + ", " + c : c));
          }),
      u
    );
  },
  Vm = Symbol("internals");
function Qn(s) {
  return s && String(s).trim().toLowerCase();
}
function wi(s) {
  return s === !1 || s == null ? s : B.isArray(s) ? s.map(wi) : String(s);
}
function Iy(s) {
  const u = Object.create(null),
    o = /([^\s,;=]+)\s*(?:=\s*([^,;]+))?/g;
  let c;
  for (; (c = o.exec(s)); ) u[c[1]] = c[2];
  return u;
}
const eb = (s) => /^[-_a-zA-Z0-9^`|~,!#$%&'*+.]+$/.test(s.trim());
function rc(s, u, o, c, f) {
  if (B.isFunction(c)) return c.call(this, u, o);
  if ((f && (u = o), !!B.isString(u))) {
    if (B.isString(c)) return u.indexOf(c) !== -1;
    if (B.isRegExp(c)) return c.test(u);
  }
}
function tb(s) {
  return s
    .trim()
    .toLowerCase()
    .replace(/([a-z\d])(\w*)/g, (u, o, c) => o.toUpperCase() + c);
}
function ab(s, u) {
  const o = B.toCamelCase(" " + u);
  ["get", "set", "has"].forEach((c) => {
    Object.defineProperty(s, c + o, {
      value: function (f, m, x) {
        return this[c].call(this, u, f, m, x);
      },
      configurable: !0,
    });
  });
}
let ct = class {
  constructor(u) {
    u && this.set(u);
  }
  set(u, o, c) {
    const f = this;
    function m(b, y, p) {
      const v = Qn(y);
      if (!v) throw new Error("header name must be a non-empty string");
      const R = B.findKey(f, v);
      (!R || f[R] === void 0 || p === !0 || (p === void 0 && f[R] !== !1)) &&
        (f[R || y] = wi(b));
    }
    const x = (b, y) => B.forEach(b, (p, v) => m(p, v, y));
    if (B.isPlainObject(u) || u instanceof this.constructor) x(u, o);
    else if (B.isString(u) && (u = u.trim()) && !eb(u)) x(Py(u), o);
    else if (B.isObject(u) && B.isIterable(u)) {
      let b = {},
        y,
        p;
      for (const v of u) {
        if (!B.isArray(v))
          throw TypeError("Object iterator must return a key-value pair");
        b[(p = v[0])] = (y = b[p])
          ? B.isArray(y)
            ? [...y, v[1]]
            : [y, v[1]]
          : v[1];
      }
      x(b, o);
    } else u != null && m(o, u, c);
    return this;
  }
  get(u, o) {
    if (((u = Qn(u)), u)) {
      const c = B.findKey(this, u);
      if (c) {
        const f = this[c];
        if (!o) return f;
        if (o === !0) return Iy(f);
        if (B.isFunction(o)) return o.call(this, f, c);
        if (B.isRegExp(o)) return o.exec(f);
        throw new TypeError("parser must be boolean|regexp|function");
      }
    }
  }
  has(u, o) {
    if (((u = Qn(u)), u)) {
      const c = B.findKey(this, u);
      return !!(c && this[c] !== void 0 && (!o || rc(this, this[c], c, o)));
    }
    return !1;
  }
  delete(u, o) {
    const c = this;
    let f = !1;
    function m(x) {
      if (((x = Qn(x)), x)) {
        const b = B.findKey(c, x);
        b && (!o || rc(c, c[b], b, o)) && (delete c[b], (f = !0));
      }
    }
    return B.isArray(u) ? u.forEach(m) : m(u), f;
  }
  clear(u) {
    const o = Object.keys(this);
    let c = o.length,
      f = !1;
    for (; c--; ) {
      const m = o[c];
      (!u || rc(this, this[m], m, u, !0)) && (delete this[m], (f = !0));
    }
    return f;
  }
  normalize(u) {
    const o = this,
      c = {};
    return (
      B.forEach(this, (f, m) => {
        const x = B.findKey(c, m);
        if (x) {
          (o[x] = wi(f)), delete o[m];
          return;
        }
        const b = u ? tb(m) : String(m).trim();
        b !== m && delete o[m], (o[b] = wi(f)), (c[b] = !0);
      }),
      this
    );
  }
  concat(...u) {
    return this.constructor.concat(this, ...u);
  }
  toJSON(u) {
    const o = Object.create(null);
    return (
      B.forEach(this, (c, f) => {
        c != null && c !== !1 && (o[f] = u && B.isArray(c) ? c.join(", ") : c);
      }),
      o
    );
  }
  [Symbol.iterator]() {
    return Object.entries(this.toJSON())[Symbol.iterator]();
  }
  toString() {
    return Object.entries(this.toJSON()).map(([u, o]) => u + ": " + o).join(`
`);
  }
  getSetCookie() {
    return this.get("set-cookie") || [];
  }
  get [Symbol.toStringTag]() {
    return "AxiosHeaders";
  }
  static from(u) {
    return u instanceof this ? u : new this(u);
  }
  static concat(u, ...o) {
    const c = new this(u);
    return o.forEach((f) => c.set(f)), c;
  }
  static accessor(u) {
    const c = (this[Vm] = this[Vm] = { accessors: {} }).accessors,
      f = this.prototype;
    function m(x) {
      const b = Qn(x);
      c[b] || (ab(f, x), (c[b] = !0));
    }
    return B.isArray(u) ? u.forEach(m) : m(u), this;
  }
};
ct.accessor([
  "Content-Type",
  "Content-Length",
  "Accept",
  "Accept-Encoding",
  "User-Agent",
  "Authorization",
]);
B.reduceDescriptors(ct.prototype, ({ value: s }, u) => {
  let o = u[0].toUpperCase() + u.slice(1);
  return {
    get: () => s,
    set(c) {
      this[o] = c;
    },
  };
});
B.freezeMethods(ct);
function uc(s, u) {
  const o = this || In,
    c = u || o,
    f = ct.from(c.headers);
  let m = c.data;
  return (
    B.forEach(s, function (b) {
      m = b.call(o, m, f.normalize(), u ? u.status : void 0);
    }),
    f.normalize(),
    m
  );
}
function Mh(s) {
  return !!(s && s.__CANCEL__);
}
function Vl(s, u, o) {
  fe.call(this, s ?? "canceled", fe.ERR_CANCELED, u, o),
    (this.name = "CanceledError");
}
B.inherits(Vl, fe, { __CANCEL__: !0 });
function Dh(s, u, o) {
  const c = o.config.validateStatus;
  !o.status || !c || c(o.status)
    ? s(o)
    : u(
        new fe(
          "Request failed with status code " + o.status,
          [fe.ERR_BAD_REQUEST, fe.ERR_BAD_RESPONSE][
            Math.floor(o.status / 100) - 4
          ],
          o.config,
          o.request,
          o
        )
      );
}
function lb(s) {
  const u = /^([-+\w]{1,25})(:?\/\/|:)/.exec(s);
  return (u && u[1]) || "";
}
function nb(s, u) {
  s = s || 10;
  const o = new Array(s),
    c = new Array(s);
  let f = 0,
    m = 0,
    x;
  return (
    (u = u !== void 0 ? u : 1e3),
    function (y) {
      const p = Date.now(),
        v = c[m];
      x || (x = p), (o[f] = y), (c[f] = p);
      let R = m,
        N = 0;
      for (; R !== f; ) (N += o[R++]), (R = R % s);
      if (((f = (f + 1) % s), f === m && (m = (m + 1) % s), p - x < u)) return;
      const L = v && p - v;
      return L ? Math.round((N * 1e3) / L) : void 0;
    }
  );
}
function sb(s, u) {
  let o = 0,
    c = 1e3 / u,
    f,
    m;
  const x = (p, v = Date.now()) => {
    (o = v), (f = null), m && (clearTimeout(m), (m = null)), s.apply(null, p);
  };
  return [
    (...p) => {
      const v = Date.now(),
        R = v - o;
      R >= c
        ? x(p, v)
        : ((f = p),
          m ||
            (m = setTimeout(() => {
              (m = null), x(f);
            }, c - R)));
    },
    () => f && x(f),
  ];
}
const _i = (s, u, o = 3) => {
    let c = 0;
    const f = nb(50, 250);
    return sb((m) => {
      const x = m.loaded,
        b = m.lengthComputable ? m.total : void 0,
        y = x - c,
        p = f(y),
        v = x <= b;
      c = x;
      const R = {
        loaded: x,
        total: b,
        progress: b ? x / b : void 0,
        bytes: y,
        rate: p || void 0,
        estimated: p && b && v ? (b - x) / p : void 0,
        event: m,
        lengthComputable: b != null,
        [u ? "download" : "upload"]: !0,
      };
      s(R);
    }, o);
  },
  Gm = (s, u) => {
    const o = s != null;
    return [(c) => u[0]({ lengthComputable: o, total: s, loaded: c }), u[1]];
  },
  Xm =
    (s) =>
    (...u) =>
      B.asap(() => s(...u)),
  ib = et.hasStandardBrowserEnv
    ? ((s, u) => (o) => (
        (o = new URL(o, et.origin)),
        s.protocol === o.protocol &&
          s.host === o.host &&
          (u || s.port === o.port)
      ))(
        new URL(et.origin),
        et.navigator && /(msie|trident)/i.test(et.navigator.userAgent)
      )
    : () => !0,
  rb = et.hasStandardBrowserEnv
    ? {
        write(s, u, o, c, f, m) {
          const x = [s + "=" + encodeURIComponent(u)];
          B.isNumber(o) && x.push("expires=" + new Date(o).toGMTString()),
            B.isString(c) && x.push("path=" + c),
            B.isString(f) && x.push("domain=" + f),
            m === !0 && x.push("secure"),
            (document.cookie = x.join("; "));
        },
        read(s) {
          const u = document.cookie.match(
            new RegExp("(^|;\\s*)(" + s + ")=([^;]*)")
          );
          return u ? decodeURIComponent(u[3]) : null;
        },
        remove(s) {
          this.write(s, "", Date.now() - 864e5);
        },
      }
    : {
        write() {},
        read() {
          return null;
        },
        remove() {},
      };
function ub(s) {
  return /^([a-z][a-z\d+\-.]*:)?\/\//i.test(s);
}
function cb(s, u) {
  return u ? s.replace(/\/?\/$/, "") + "/" + u.replace(/^\/+/, "") : s;
}
function zh(s, u, o) {
  let c = !ub(u);
  return s && (c || o == !1) ? cb(s, u) : u;
}
const Qm = (s) => (s instanceof ct ? { ...s } : s);
function Pa(s, u) {
  u = u || {};
  const o = {};
  function c(p, v, R, N) {
    return B.isPlainObject(p) && B.isPlainObject(v)
      ? B.merge.call({ caseless: N }, p, v)
      : B.isPlainObject(v)
      ? B.merge({}, v)
      : B.isArray(v)
      ? v.slice()
      : v;
  }
  function f(p, v, R, N) {
    if (B.isUndefined(v)) {
      if (!B.isUndefined(p)) return c(void 0, p, R, N);
    } else return c(p, v, R, N);
  }
  function m(p, v) {
    if (!B.isUndefined(v)) return c(void 0, v);
  }
  function x(p, v) {
    if (B.isUndefined(v)) {
      if (!B.isUndefined(p)) return c(void 0, p);
    } else return c(void 0, v);
  }
  function b(p, v, R) {
    if (R in u) return c(p, v);
    if (R in s) return c(void 0, p);
  }
  const y = {
    url: m,
    method: m,
    data: m,
    baseURL: x,
    transformRequest: x,
    transformResponse: x,
    paramsSerializer: x,
    timeout: x,
    timeoutMessage: x,
    withCredentials: x,
    withXSRFToken: x,
    adapter: x,
    responseType: x,
    xsrfCookieName: x,
    xsrfHeaderName: x,
    onUploadProgress: x,
    onDownloadProgress: x,
    decompress: x,
    maxContentLength: x,
    maxBodyLength: x,
    beforeRedirect: x,
    transport: x,
    httpAgent: x,
    httpsAgent: x,
    cancelToken: x,
    socketPath: x,
    responseEncoding: x,
    validateStatus: b,
    headers: (p, v, R) => f(Qm(p), Qm(v), R, !0),
  };
  return (
    B.forEach(Object.keys(Object.assign({}, s, u)), function (v) {
      const R = y[v] || f,
        N = R(s[v], u[v], v);
      (B.isUndefined(N) && R !== b) || (o[v] = N);
    }),
    o
  );
}
const Uh = (s) => {
    const u = Pa({}, s);
    let {
      data: o,
      withXSRFToken: c,
      xsrfHeaderName: f,
      xsrfCookieName: m,
      headers: x,
      auth: b,
    } = u;
    (u.headers = x = ct.from(x)),
      (u.url = Rh(
        zh(u.baseURL, u.url, u.allowAbsoluteUrls),
        s.params,
        s.paramsSerializer
      )),
      b &&
        x.set(
          "Authorization",
          "Basic " +
            btoa(
              (b.username || "") +
                ":" +
                (b.password ? unescape(encodeURIComponent(b.password)) : "")
            )
        );
    let y;
    if (B.isFormData(o)) {
      if (et.hasStandardBrowserEnv || et.hasStandardBrowserWebWorkerEnv)
        x.setContentType(void 0);
      else if ((y = x.getContentType()) !== !1) {
        const [p, ...v] = y
          ? y
              .split(";")
              .map((R) => R.trim())
              .filter(Boolean)
          : [];
        x.setContentType([p || "multipart/form-data", ...v].join("; "));
      }
    }
    if (
      et.hasStandardBrowserEnv &&
      (c && B.isFunction(c) && (c = c(u)), c || (c !== !1 && ib(u.url)))
    ) {
      const p = f && m && rb.read(m);
      p && x.set(f, p);
    }
    return u;
  },
  ob = typeof XMLHttpRequest < "u",
  fb =
    ob &&
    function (s) {
      return new Promise(function (o, c) {
        const f = Uh(s);
        let m = f.data;
        const x = ct.from(f.headers).normalize();
        let { responseType: b, onUploadProgress: y, onDownloadProgress: p } = f,
          v,
          R,
          N,
          L,
          w;
        function C() {
          L && L(),
            w && w(),
            f.cancelToken && f.cancelToken.unsubscribe(v),
            f.signal && f.signal.removeEventListener("abort", v);
        }
        let D = new XMLHttpRequest();
        D.open(f.method.toUpperCase(), f.url, !0), (D.timeout = f.timeout);
        function Y() {
          if (!D) return;
          const U = ct.from(
              "getAllResponseHeaders" in D && D.getAllResponseHeaders()
            ),
            Q = {
              data:
                !b || b === "text" || b === "json"
                  ? D.responseText
                  : D.response,
              status: D.status,
              statusText: D.statusText,
              headers: U,
              config: s,
              request: D,
            };
          Dh(
            function (re) {
              o(re), C();
            },
            function (re) {
              c(re), C();
            },
            Q
          ),
            (D = null);
        }
        "onloadend" in D
          ? (D.onloadend = Y)
          : (D.onreadystatechange = function () {
              !D ||
                D.readyState !== 4 ||
                (D.status === 0 &&
                  !(D.responseURL && D.responseURL.indexOf("file:") === 0)) ||
                setTimeout(Y);
            }),
          (D.onabort = function () {
            D &&
              (c(new fe("Request aborted", fe.ECONNABORTED, s, D)), (D = null));
          }),
          (D.onerror = function () {
            c(new fe("Network Error", fe.ERR_NETWORK, s, D)), (D = null);
          }),
          (D.ontimeout = function () {
            let q = f.timeout
              ? "timeout of " + f.timeout + "ms exceeded"
              : "timeout exceeded";
            const Q = f.transitional || Ch;
            f.timeoutErrorMessage && (q = f.timeoutErrorMessage),
              c(
                new fe(
                  q,
                  Q.clarifyTimeoutError ? fe.ETIMEDOUT : fe.ECONNABORTED,
                  s,
                  D
                )
              ),
              (D = null);
          }),
          m === void 0 && x.setContentType(null),
          "setRequestHeader" in D &&
            B.forEach(x.toJSON(), function (q, Q) {
              D.setRequestHeader(Q, q);
            }),
          B.isUndefined(f.withCredentials) ||
            (D.withCredentials = !!f.withCredentials),
          b && b !== "json" && (D.responseType = f.responseType),
          p && (([N, w] = _i(p, !0)), D.addEventListener("progress", N)),
          y &&
            D.upload &&
            (([R, L] = _i(y)),
            D.upload.addEventListener("progress", R),
            D.upload.addEventListener("loadend", L)),
          (f.cancelToken || f.signal) &&
            ((v = (U) => {
              D &&
                (c(!U || U.type ? new Vl(null, s, D) : U),
                D.abort(),
                (D = null));
            }),
            f.cancelToken && f.cancelToken.subscribe(v),
            f.signal &&
              (f.signal.aborted ? v() : f.signal.addEventListener("abort", v)));
        const Z = lb(f.url);
        if (Z && et.protocols.indexOf(Z) === -1) {
          c(new fe("Unsupported protocol " + Z + ":", fe.ERR_BAD_REQUEST, s));
          return;
        }
        D.send(m || null);
      });
    },
  db = (s, u) => {
    const { length: o } = (s = s ? s.filter(Boolean) : []);
    if (u || o) {
      let c = new AbortController(),
        f;
      const m = function (p) {
        if (!f) {
          (f = !0), b();
          const v = p instanceof Error ? p : this.reason;
          c.abort(
            v instanceof fe ? v : new Vl(v instanceof Error ? v.message : v)
          );
        }
      };
      let x =
        u &&
        setTimeout(() => {
          (x = null), m(new fe(`timeout ${u} of ms exceeded`, fe.ETIMEDOUT));
        }, u);
      const b = () => {
        s &&
          (x && clearTimeout(x),
          (x = null),
          s.forEach((p) => {
            p.unsubscribe
              ? p.unsubscribe(m)
              : p.removeEventListener("abort", m);
          }),
          (s = null));
      };
      s.forEach((p) => p.addEventListener("abort", m));
      const { signal: y } = c;
      return (y.unsubscribe = () => B.asap(b)), y;
    }
  },
  mb = function* (s, u) {
    let o = s.byteLength;
    if (o < u) {
      yield s;
      return;
    }
    let c = 0,
      f;
    for (; c < o; ) (f = c + u), yield s.slice(c, f), (c = f);
  },
  hb = async function* (s, u) {
    for await (const o of pb(s)) yield* mb(o, u);
  },
  pb = async function* (s) {
    if (s[Symbol.asyncIterator]) {
      yield* s;
      return;
    }
    const u = s.getReader();
    try {
      for (;;) {
        const { done: o, value: c } = await u.read();
        if (o) break;
        yield c;
      }
    } finally {
      await u.cancel();
    }
  },
  Zm = (s, u, o, c) => {
    const f = hb(s, u);
    let m = 0,
      x,
      b = (y) => {
        x || ((x = !0), c && c(y));
      };
    return new ReadableStream(
      {
        async pull(y) {
          try {
            const { done: p, value: v } = await f.next();
            if (p) {
              b(), y.close();
              return;
            }
            let R = v.byteLength;
            if (o) {
              let N = (m += R);
              o(N);
            }
            y.enqueue(new Uint8Array(v));
          } catch (p) {
            throw (b(p), p);
          }
        },
        cancel(y) {
          return b(y), f.return();
        },
      },
      { highWaterMark: 2 }
    );
  },
  ki =
    typeof fetch == "function" &&
    typeof Request == "function" &&
    typeof Response == "function",
  kh = ki && typeof ReadableStream == "function",
  xb =
    ki &&
    (typeof TextEncoder == "function"
      ? (
          (s) => (u) =>
            s.encode(u)
        )(new TextEncoder())
      : async (s) => new Uint8Array(await new Response(s).arrayBuffer())),
  Lh = (s, ...u) => {
    try {
      return !!s(...u);
    } catch {
      return !1;
    }
  },
  gb =
    kh &&
    Lh(() => {
      let s = !1;
      const u = new Request(et.origin, {
        body: new ReadableStream(),
        method: "POST",
        get duplex() {
          return (s = !0), "half";
        },
      }).headers.has("Content-Type");
      return s && !u;
    }),
  Km = 64 * 1024,
  xc = kh && Lh(() => B.isReadableStream(new Response("").body)),
  Ai = { stream: xc && ((s) => s.body) };
ki &&
  ((s) => {
    ["text", "arrayBuffer", "blob", "formData", "stream"].forEach((u) => {
      !Ai[u] &&
        (Ai[u] = B.isFunction(s[u])
          ? (o) => o[u]()
          : (o, c) => {
              throw new fe(
                `Response type '${u}' is not supported`,
                fe.ERR_NOT_SUPPORT,
                c
              );
            });
    });
  })(new Response());
const yb = async (s) => {
    if (s == null) return 0;
    if (B.isBlob(s)) return s.size;
    if (B.isSpecCompliantForm(s))
      return (
        await new Request(et.origin, { method: "POST", body: s }).arrayBuffer()
      ).byteLength;
    if (B.isArrayBufferView(s) || B.isArrayBuffer(s)) return s.byteLength;
    if ((B.isURLSearchParams(s) && (s = s + ""), B.isString(s)))
      return (await xb(s)).byteLength;
  },
  bb = async (s, u) => {
    const o = B.toFiniteNumber(s.getContentLength());
    return o ?? yb(u);
  },
  vb =
    ki &&
    (async (s) => {
      let {
        url: u,
        method: o,
        data: c,
        signal: f,
        cancelToken: m,
        timeout: x,
        onDownloadProgress: b,
        onUploadProgress: y,
        responseType: p,
        headers: v,
        withCredentials: R = "same-origin",
        fetchOptions: N,
      } = Uh(s);
      p = p ? (p + "").toLowerCase() : "text";
      let L = db([f, m && m.toAbortSignal()], x),
        w;
      const C =
        L &&
        L.unsubscribe &&
        (() => {
          L.unsubscribe();
        });
      let D;
      try {
        if (
          y &&
          gb &&
          o !== "get" &&
          o !== "head" &&
          (D = await bb(v, c)) !== 0
        ) {
          let Q = new Request(u, { method: "POST", body: c, duplex: "half" }),
            W;
          if (
            (B.isFormData(c) &&
              (W = Q.headers.get("content-type")) &&
              v.setContentType(W),
            Q.body)
          ) {
            const [re, ue] = Gm(D, _i(Xm(y)));
            c = Zm(Q.body, Km, re, ue);
          }
        }
        B.isString(R) || (R = R ? "include" : "omit");
        const Y = "credentials" in Request.prototype;
        w = new Request(u, {
          ...N,
          signal: L,
          method: o.toUpperCase(),
          headers: v.normalize().toJSON(),
          body: c,
          duplex: "half",
          credentials: Y ? R : void 0,
        });
        let Z = await fetch(w, N);
        const U = xc && (p === "stream" || p === "response");
        if (xc && (b || (U && C))) {
          const Q = {};
          ["status", "statusText", "headers"].forEach((ce) => {
            Q[ce] = Z[ce];
          });
          const W = B.toFiniteNumber(Z.headers.get("content-length")),
            [re, ue] = (b && Gm(W, _i(Xm(b), !0))) || [];
          Z = new Response(
            Zm(Z.body, Km, re, () => {
              ue && ue(), C && C();
            }),
            Q
          );
        }
        p = p || "text";
        let q = await Ai[B.findKey(Ai, p) || "text"](Z, s);
        return (
          !U && C && C(),
          await new Promise((Q, W) => {
            Dh(Q, W, {
              data: q,
              headers: ct.from(Z.headers),
              status: Z.status,
              statusText: Z.statusText,
              config: s,
              request: w,
            });
          })
        );
      } catch (Y) {
        throw (
          (C && C(),
          Y && Y.name === "TypeError" && /Load failed|fetch/i.test(Y.message)
            ? Object.assign(new fe("Network Error", fe.ERR_NETWORK, s, w), {
                cause: Y.cause || Y,
              })
            : fe.from(Y, Y && Y.code, s, w))
        );
      }
    }),
  gc = { http: Uy, xhr: fb, fetch: vb };
B.forEach(gc, (s, u) => {
  if (s) {
    try {
      Object.defineProperty(s, "name", { value: u });
    } catch {}
    Object.defineProperty(s, "adapterName", { value: u });
  }
});
const Jm = (s) => `- ${s}`,
  jb = (s) => B.isFunction(s) || s === null || s === !1,
  Bh = {
    getAdapter: (s) => {
      s = B.isArray(s) ? s : [s];
      const { length: u } = s;
      let o, c;
      const f = {};
      for (let m = 0; m < u; m++) {
        o = s[m];
        let x;
        if (
          ((c = o),
          !jb(o) && ((c = gc[(x = String(o)).toLowerCase()]), c === void 0))
        )
          throw new fe(`Unknown adapter '${x}'`);
        if (c) break;
        f[x || "#" + m] = c;
      }
      if (!c) {
        const m = Object.entries(f).map(
          ([b, y]) =>
            `adapter ${b} ` +
            (y === !1
              ? "is not supported by the environment"
              : "is not available in the build")
        );
        let x = u
          ? m.length > 1
            ? `since :
` +
              m.map(Jm).join(`
`)
            : " " + Jm(m[0])
          : "as no adapter specified";
        throw new fe(
          "There is no suitable adapter to dispatch the request " + x,
          "ERR_NOT_SUPPORT"
        );
      }
      return c;
    },
    adapters: gc,
  };
function cc(s) {
  if (
    (s.cancelToken && s.cancelToken.throwIfRequested(),
    s.signal && s.signal.aborted)
  )
    throw new Vl(null, s);
}
function $m(s) {
  return (
    cc(s),
    (s.headers = ct.from(s.headers)),
    (s.data = uc.call(s, s.transformRequest)),
    ["post", "put", "patch"].indexOf(s.method) !== -1 &&
      s.headers.setContentType("application/x-www-form-urlencoded", !1),
    Bh.getAdapter(s.adapter || In.adapter)(s).then(
      function (c) {
        return (
          cc(s),
          (c.data = uc.call(s, s.transformResponse, c)),
          (c.headers = ct.from(c.headers)),
          c
        );
      },
      function (c) {
        return (
          Mh(c) ||
            (cc(s),
            c &&
              c.response &&
              ((c.response.data = uc.call(s, s.transformResponse, c.response)),
              (c.response.headers = ct.from(c.response.headers)))),
          Promise.reject(c)
        );
      }
    )
  );
}
const Hh = "1.10.0",
  Li = {};
["object", "boolean", "number", "function", "string", "symbol"].forEach(
  (s, u) => {
    Li[s] = function (c) {
      return typeof c === s || "a" + (u < 1 ? "n " : " ") + s;
    };
  }
);
const Fm = {};
Li.transitional = function (u, o, c) {
  function f(m, x) {
    return (
      "[Axios v" +
      Hh +
      "] Transitional option '" +
      m +
      "'" +
      x +
      (c ? ". " + c : "")
    );
  }
  return (m, x, b) => {
    if (u === !1)
      throw new fe(
        f(x, " has been removed" + (o ? " in " + o : "")),
        fe.ERR_DEPRECATED
      );
    return (
      o &&
        !Fm[x] &&
        ((Fm[x] = !0),
        console.warn(
          f(
            x,
            " has been deprecated since v" +
              o +
              " and will be removed in the near future"
          )
        )),
      u ? u(m, x, b) : !0
    );
  };
};
Li.spelling = function (u) {
  return (o, c) => (console.warn(`${c} is likely a misspelling of ${u}`), !0);
};
function Sb(s, u, o) {
  if (typeof s != "object")
    throw new fe("options must be an object", fe.ERR_BAD_OPTION_VALUE);
  const c = Object.keys(s);
  let f = c.length;
  for (; f-- > 0; ) {
    const m = c[f],
      x = u[m];
    if (x) {
      const b = s[m],
        y = b === void 0 || x(b, m, s);
      if (y !== !0)
        throw new fe("option " + m + " must be " + y, fe.ERR_BAD_OPTION_VALUE);
      continue;
    }
    if (o !== !0) throw new fe("Unknown option " + m, fe.ERR_BAD_OPTION);
  }
}
const Ei = { assertOptions: Sb, validators: Li },
  Ht = Ei.validators;
let Wa = class {
  constructor(u) {
    (this.defaults = u || {}),
      (this.interceptors = { request: new Ym(), response: new Ym() });
  }
  async request(u, o) {
    try {
      return await this._request(u, o);
    } catch (c) {
      if (c instanceof Error) {
        let f = {};
        Error.captureStackTrace
          ? Error.captureStackTrace(f)
          : (f = new Error());
        const m = f.stack ? f.stack.replace(/^.+\n/, "") : "";
        try {
          c.stack
            ? m &&
              !String(c.stack).endsWith(m.replace(/^.+\n.+\n/, "")) &&
              (c.stack +=
                `
` + m)
            : (c.stack = m);
        } catch {}
      }
      throw c;
    }
  }
  _request(u, o) {
    typeof u == "string" ? ((o = o || {}), (o.url = u)) : (o = u || {}),
      (o = Pa(this.defaults, o));
    const { transitional: c, paramsSerializer: f, headers: m } = o;
    c !== void 0 &&
      Ei.assertOptions(
        c,
        {
          silentJSONParsing: Ht.transitional(Ht.boolean),
          forcedJSONParsing: Ht.transitional(Ht.boolean),
          clarifyTimeoutError: Ht.transitional(Ht.boolean),
        },
        !1
      ),
      f != null &&
        (B.isFunction(f)
          ? (o.paramsSerializer = { serialize: f })
          : Ei.assertOptions(
              f,
              { encode: Ht.function, serialize: Ht.function },
              !0
            )),
      o.allowAbsoluteUrls !== void 0 ||
        (this.defaults.allowAbsoluteUrls !== void 0
          ? (o.allowAbsoluteUrls = this.defaults.allowAbsoluteUrls)
          : (o.allowAbsoluteUrls = !0)),
      Ei.assertOptions(
        o,
        {
          baseUrl: Ht.spelling("baseURL"),
          withXsrfToken: Ht.spelling("withXSRFToken"),
        },
        !0
      ),
      (o.method = (o.method || this.defaults.method || "get").toLowerCase());
    let x = m && B.merge(m.common, m[o.method]);
    m &&
      B.forEach(
        ["delete", "get", "head", "post", "put", "patch", "common"],
        (w) => {
          delete m[w];
        }
      ),
      (o.headers = ct.concat(x, m));
    const b = [];
    let y = !0;
    this.interceptors.request.forEach(function (C) {
      (typeof C.runWhen == "function" && C.runWhen(o) === !1) ||
        ((y = y && C.synchronous), b.unshift(C.fulfilled, C.rejected));
    });
    const p = [];
    this.interceptors.response.forEach(function (C) {
      p.push(C.fulfilled, C.rejected);
    });
    let v,
      R = 0,
      N;
    if (!y) {
      const w = [$m.bind(this), void 0];
      for (
        w.unshift.apply(w, b),
          w.push.apply(w, p),
          N = w.length,
          v = Promise.resolve(o);
        R < N;

      )
        v = v.then(w[R++], w[R++]);
      return v;
    }
    N = b.length;
    let L = o;
    for (R = 0; R < N; ) {
      const w = b[R++],
        C = b[R++];
      try {
        L = w(L);
      } catch (D) {
        C.call(this, D);
        break;
      }
    }
    try {
      v = $m.call(this, L);
    } catch (w) {
      return Promise.reject(w);
    }
    for (R = 0, N = p.length; R < N; ) v = v.then(p[R++], p[R++]);
    return v;
  }
  getUri(u) {
    u = Pa(this.defaults, u);
    const o = zh(u.baseURL, u.url, u.allowAbsoluteUrls);
    return Rh(o, u.params, u.paramsSerializer);
  }
};
B.forEach(["delete", "get", "head", "options"], function (u) {
  Wa.prototype[u] = function (o, c) {
    return this.request(
      Pa(c || {}, { method: u, url: o, data: (c || {}).data })
    );
  };
});
B.forEach(["post", "put", "patch"], function (u) {
  function o(c) {
    return function (m, x, b) {
      return this.request(
        Pa(b || {}, {
          method: u,
          headers: c ? { "Content-Type": "multipart/form-data" } : {},
          url: m,
          data: x,
        })
      );
    };
  }
  (Wa.prototype[u] = o()), (Wa.prototype[u + "Form"] = o(!0));
});
let Nb = class qh {
  constructor(u) {
    if (typeof u != "function")
      throw new TypeError("executor must be a function.");
    let o;
    this.promise = new Promise(function (m) {
      o = m;
    });
    const c = this;
    this.promise.then((f) => {
      if (!c._listeners) return;
      let m = c._listeners.length;
      for (; m-- > 0; ) c._listeners[m](f);
      c._listeners = null;
    }),
      (this.promise.then = (f) => {
        let m;
        const x = new Promise((b) => {
          c.subscribe(b), (m = b);
        }).then(f);
        return (
          (x.cancel = function () {
            c.unsubscribe(m);
          }),
          x
        );
      }),
      u(function (m, x, b) {
        c.reason || ((c.reason = new Vl(m, x, b)), o(c.reason));
      });
  }
  throwIfRequested() {
    if (this.reason) throw this.reason;
  }
  subscribe(u) {
    if (this.reason) {
      u(this.reason);
      return;
    }
    this._listeners ? this._listeners.push(u) : (this._listeners = [u]);
  }
  unsubscribe(u) {
    if (!this._listeners) return;
    const o = this._listeners.indexOf(u);
    o !== -1 && this._listeners.splice(o, 1);
  }
  toAbortSignal() {
    const u = new AbortController(),
      o = (c) => {
        u.abort(c);
      };
    return (
      this.subscribe(o),
      (u.signal.unsubscribe = () => this.unsubscribe(o)),
      u.signal
    );
  }
  static source() {
    let u;
    return {
      token: new qh(function (f) {
        u = f;
      }),
      cancel: u,
    };
  }
};
function wb(s) {
  return function (o) {
    return s.apply(null, o);
  };
}
function Eb(s) {
  return B.isObject(s) && s.isAxiosError === !0;
}
const yc = {
  Continue: 100,
  SwitchingProtocols: 101,
  Processing: 102,
  EarlyHints: 103,
  Ok: 200,
  Created: 201,
  Accepted: 202,
  NonAuthoritativeInformation: 203,
  NoContent: 204,
  ResetContent: 205,
  PartialContent: 206,
  MultiStatus: 207,
  AlreadyReported: 208,
  ImUsed: 226,
  MultipleChoices: 300,
  MovedPermanently: 301,
  Found: 302,
  SeeOther: 303,
  NotModified: 304,
  UseProxy: 305,
  Unused: 306,
  TemporaryRedirect: 307,
  PermanentRedirect: 308,
  BadRequest: 400,
  Unauthorized: 401,
  PaymentRequired: 402,
  Forbidden: 403,
  NotFound: 404,
  MethodNotAllowed: 405,
  NotAcceptable: 406,
  ProxyAuthenticationRequired: 407,
  RequestTimeout: 408,
  Conflict: 409,
  Gone: 410,
  LengthRequired: 411,
  PreconditionFailed: 412,
  PayloadTooLarge: 413,
  UriTooLong: 414,
  UnsupportedMediaType: 415,
  RangeNotSatisfiable: 416,
  ExpectationFailed: 417,
  ImATeapot: 418,
  MisdirectedRequest: 421,
  UnprocessableEntity: 422,
  Locked: 423,
  FailedDependency: 424,
  TooEarly: 425,
  UpgradeRequired: 426,
  PreconditionRequired: 428,
  TooManyRequests: 429,
  RequestHeaderFieldsTooLarge: 431,
  UnavailableForLegalReasons: 451,
  InternalServerError: 500,
  NotImplemented: 501,
  BadGateway: 502,
  ServiceUnavailable: 503,
  GatewayTimeout: 504,
  HttpVersionNotSupported: 505,
  VariantAlsoNegotiates: 506,
  InsufficientStorage: 507,
  LoopDetected: 508,
  NotExtended: 510,
  NetworkAuthenticationRequired: 511,
};
Object.entries(yc).forEach(([s, u]) => {
  yc[u] = s;
});
function Yh(s) {
  const u = new Wa(s),
    o = gh(Wa.prototype.request, u);
  return (
    B.extend(o, Wa.prototype, u, { allOwnKeys: !0 }),
    B.extend(o, u, null, { allOwnKeys: !0 }),
    (o.create = function (f) {
      return Yh(Pa(s, f));
    }),
    o
  );
}
const be = Yh(In);
be.Axios = Wa;
be.CanceledError = Vl;
be.CancelToken = Nb;
be.isCancel = Mh;
be.VERSION = Hh;
be.toFormData = Ui;
be.AxiosError = fe;
be.Cancel = be.CanceledError;
be.all = function (u) {
  return Promise.all(u);
};
be.spread = wb;
be.isAxiosError = Eb;
be.mergeConfig = Pa;
be.AxiosHeaders = ct;
be.formToJSON = (s) => Oh(B.isHTMLForm(s) ? new FormData(s) : s);
be.getAdapter = Bh.getAdapter;
be.HttpStatusCode = yc;
be.default = be;
const {
    Axios: kb,
    AxiosError: Lb,
    CanceledError: Bb,
    isCancel: Hb,
    CancelToken: qb,
    VERSION: Yb,
    all: Vb,
    Cancel: Gb,
    isAxiosError: Xb,
    spread: Qb,
    toFormData: Zb,
    AxiosHeaders: Kb,
    HttpStatusCode: Jb,
    formToJSON: $b,
    getAdapter: Fb,
    mergeConfig: Wb,
  } = be,
  bt = "/backend/routes/api.php",
  Tb = () => {
    const [s, u] = T.useState([]),
      [o, c] = T.useState([]),
      [f, m] = T.useState(!0),
      [x, b] = T.useState(null),
      [y, p] = T.useState({}),
      [v, R] = T.useState({}),
      [N, L] = T.useState({}),
      [w, C] = T.useState({ page: 1, rowsPerPage: 10, total: 0 });
    T.useEffect(() => {
      if (x) {
        const S = setTimeout(() => b(null), 3e3);
        return () => clearTimeout(S);
      }
    }, [x]),
      T.useEffect(() => {
        (async () => {
          try {
            const H = await be.post(`${bt}/api/master/campaigns_master`, {
              action: "list",
            });
            u(H.data.data.campaigns || []),
              c(H.data.data.smtp_servers || []),
              C(($) => ({ ...$, total: (H.data.data.campaigns || []).length })),
              m(!1);
          } catch {
            b({ type: "error", text: "Failed to load data" }), m(!1);
          }
        })();
      }, []);
    const D = Math.max(1, Math.ceil(w.total / w.rowsPerPage)),
      Y = s.slice((w.page - 1) * w.rowsPerPage, w.page * w.rowsPerPage),
      Z = async (S) => {
        const H = !y[S];
        p(($) => ({ ...$, [S]: H })), H && (await U(S), await q(S));
      },
      U = async (S) => {
        try {
          const H = await be.post(`${bt}/api/master/campaigns_master`, {
            action: "email_counts",
            campaign_id: S,
          });
          L(($) => ({ ...$, [S]: H.data.data }));
        } catch {
          b({ type: "error", text: "Failed to fetch email counts" });
        }
      },
      q = async (S) => {
        try {
          const H = await be.post(`${bt}/api/master/campaigns_master`, {
            action: "get_distribution",
            campaign_id: S,
          });
          R(($) => ({ ...$, [S]: H.data.data }));
        } catch {
          b({ type: "error", text: "Failed to fetch distributions" });
        }
      },
      Q = (S) => {
        const H = s.find((g) => g.campaign_id === S);
        if (!H) return;
        const ee =
          100 -
          (v[S] || []).reduce((g, M) => g + (parseFloat(M.percentage) || 0), 0);
        if (ee <= 0) {
          b({
            type: "error",
            text: "You have already allocated 100% of emails",
          });
          return;
        }
        if (!o.length) {
          b({ type: "error", text: "No SMTP servers available" });
          return;
        }
        R((g) => {
          var M;
          return {
            ...g,
            [S]: [
              ...(g[S] || []),
              {
                smtp_id: ((M = o[0]) == null ? void 0 : M.id) || "",
                percentage: Math.min(10, ee).toFixed(1),
                email_count: Math.floor(
                  (H.valid_emails * Math.min(10, ee)) / 100
                ),
              },
            ],
          };
        });
      },
      W = (S, H) => {
        R(($) => ({ ...$, [S]: $[S].filter((ee, g) => g !== H) }));
      },
      re = (S, H, $, ee) => {
        const g = s.find((M) => M.campaign_id === S);
        g &&
          R((M) => {
            const J = M[S].map((K, P) => (P === H ? { ...K, [$]: ee } : K));
            if ($ === "percentage") {
              let K = parseFloat(ee) || 0;
              K < 1 && (K = 1);
              const le =
                100 -
                J.reduce(
                  (te, tt, Re) =>
                    te + (Re === H ? 0 : parseFloat(tt.percentage) || 0),
                  0
                );
              K > le && (K = le),
                (J[H].percentage = K),
                (J[H].email_count = Math.floor((g.valid_emails * K) / 100));
            }
            return { ...M, [S]: J };
          });
      },
      ue = (S) => {
        const H = s.find(($) => $.campaign_id === S);
        if (!H) return 0;
        if (v[S] && v[S].length > 0) {
          const $ = v[S].reduce(
            (ee, g) => ee + (parseFloat(g.percentage) || 0),
            0
          );
          return Math.max(0, 100 - $);
        } else
          return Math.max(0, 100 - (parseFloat(H.distributed_percentage) || 0));
      },
      ce = async (S) => {
        var H, $;
        try {
          const ee = (v[S] || []).reduce(
            (J, K) => J + (parseFloat(K.percentage) || 0),
            0
          );
          if (ee > 100) {
            b({
              type: "error",
              text: `Total distribution percentage cannot exceed 100% (Current: ${ee.toFixed(
                1
              )}%)`,
            });
            return;
          }
          const g = await be.post(`${bt}/api/master/campaigns_master`, {
            action: "distribute",
            campaign_id: S,
            distribution: v[S],
          });
          b({ type: "success", text: g.data.message });
          const M = await be.post(`${bt}/api/master/campaigns_master`, {
            action: "list",
          });
          u(M.data.data.campaigns || []);
        } catch (ee) {
          b({
            type: "error",
            text:
              (($ = (H = ee.response) == null ? void 0 : H.data) == null
                ? void 0
                : $.error) || "Failed to save distribution",
          });
        }
      },
      je = async (S) => {
        var H, $;
        try {
          const ee = await be.post(`${bt}/api/master/campaigns_master`, {
            action: "auto_distribute",
            campaign_id: S,
          });
          b({ type: "success", text: ee.data.message }), await q(S);
          const g = await be.post(`${bt}/api/master/campaigns_master`, {
            action: "list",
          });
          u(g.data.data.campaigns || []);
        } catch (ee) {
          b({
            type: "error",
            text:
              (($ = (H = ee.response) == null ? void 0 : H.data) == null
                ? void 0
                : $.error) || "Failed to auto-distribute",
          });
        }
      },
      Ue = async (S) => {
        var H, $;
        try {
          const ee = await be.post(`${bt}/api/master/campaigns_master`, {
            action: "start_campaign",
            campaign_id: S,
          });
          b({ type: "success", text: ee.data.message });
          const g = await be.post(`${bt}/api/master/campaigns_master`, {
            action: "list",
          });
          u(g.data.data.campaigns || []);
        } catch (ee) {
          b({
            type: "error",
            text:
              (($ = (H = ee.response) == null ? void 0 : H.data) == null
                ? void 0
                : $.error) || "Failed to start campaign",
          });
        }
      },
      Me = async (S) => {
        var H, $;
        try {
          const ee = await be.post(`${bt}/api/master/campaigns_master`, {
            action: "pause_campaign",
            campaign_id: S,
          });
          b({ type: "success", text: ee.data.message });
          const g = await be.post(`${bt}/api/master/campaigns_master`, {
            action: "list",
          });
          u(g.data.data.campaigns || []);
        } catch (ee) {
          b({
            type: "error",
            text:
              (($ = (H = ee.response) == null ? void 0 : H.data) == null
                ? void 0
                : $.error) || "Failed to pause campaign",
          });
        }
      },
      F = async (S) => {
        var H, $;
        try {
          const ee = await be.post(`${bt}/api/master/campaigns_master`, {
            action: "retry_failed",
            campaign_id: S,
          });
          b({ type: "success", text: ee.data.message });
          const g = await be.post(`${bt}/api/master/campaigns_master`, {
            action: "list",
          });
          u(g.data.data.campaigns || []);
        } catch (ee) {
          b({
            type: "error",
            text:
              (($ = (H = ee.response) == null ? void 0 : H.data) == null
                ? void 0
                : $.error) || "Failed to retry failed emails",
          });
        }
      },
      oe = ({ status: S }) => {
        const H = (S || "").toLowerCase(),
          $ = S || "Not started";
        return r.jsx("span", {
          className: `px-2 py-1 rounded text-xs font-semibold ${
            H === "running"
              ? "bg-blue-500 text-white"
              : H === "paused"
              ? "bg-gray-500 text-white"
              : H === "completed"
              ? "bg-green-500 text-white"
              : H === "failed"
              ? "bg-red-500 text-white"
              : "bg-yellow-500 text-white"
          }`,
          children: $,
        });
      },
      pe = ({ message: S, onClose: H }) =>
        S
          ? r.jsxs("div", {
              className: `fixed top-6 left-1/2 transform -translate-x-1/2 z-50
        px-6 py-3 rounded-xl shadow text-base font-semibold
        flex items-center gap-3
        ${
          S.type === "error"
            ? "bg-red-200/60 border border-red-400 text-red-800"
            : "bg-green-200/60 border border-green-400 text-green-800"
        }`,
              style: {
                minWidth: 250,
                maxWidth: 400,
                boxShadow: "0 8px 32px 0 rgba(0, 0, 0, 0.23)",
                backdropFilter: "blur(8px)",
                WebkitBackdropFilter: "blur(8px)",
              },
              role: "alert",
              children: [
                r.jsx("i", {
                  className: `fas text-lg ${
                    S.type === "error"
                      ? "fa-exclamation-circle text-red-500"
                      : "fa-check-circle text-green-500"
                  }`,
                }),
                r.jsx("span", { className: "flex-1", children: S.text }),
                r.jsx("button", {
                  onClick: H,
                  className:
                    "ml-2 text-gray-500 hover:text-gray-700 focus:outline-none",
                  "aria-label": "Close",
                  children: r.jsx("i", { className: "fas fa-times" }),
                }),
              ],
            })
          : null;
    return f
      ? r.jsx("div", {
          className: "flex justify-center items-center h-screen",
          children: r.jsx("div", {
            className:
              "animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500",
          }),
        })
      : r.jsx("div", {
          className: "bg-gray-100 min-h-screen mt-14",
          children: r.jsxs("div", {
            className: "container mx-auto px-2 sm:px-4 py-6 w-full max-w-7xl",
            children: [
              r.jsx(pe, { message: x, onClose: () => b(null) }),
              r.jsx("div", {
                className: "grid grid-cols-1 gap-4 sm:gap-6",
                children: Y.map((S) => {
                  var ee;
                  const H = ue(S.campaign_id),
                    $ = v[S.campaign_id] || [];
                  return (
                    N[S.campaign_id],
                    r.jsx(
                      "div",
                      {
                        className:
                          "bg-white rounded-xl shadow-md overflow-hidden transition-all hover:shadow-lg",
                        children: r.jsxs("div", {
                          className: "p-4 sm:p-6",
                          children: [
                            r.jsxs("div", {
                              className:
                                "flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3",
                              children: [
                                r.jsxs("div", {
                                  className: "flex-1 min-w-0",
                                  children: [
                                    r.jsx("h2", {
                                      className:
                                        "text-lg sm:text-xl font-semibold text-gray-800 mb-1 break-words",
                                      children: S.description,
                                    }),
                                    r.jsx("p", {
                                      className:
                                        "text-xs sm:text-sm text-gray-600 mb-2 break-words",
                                      children: S.mail_subject,
                                    }),
                                    r.jsxs("div", {
                                      className:
                                        "flex flex-wrap items-center gap-2 sm:gap-4",
                                      children: [
                                        r.jsxs("span", {
                                          className:
                                            "inline-flex items-center px-2.5 py-1 rounded-full bg-green-100 text-green-800 text-xs sm:text-sm font-medium",
                                          children: [
                                            r.jsx("i", {
                                              className: "fas fa-envelope mr-1",
                                            }),
                                            (ee = Number(S.valid_emails)) ==
                                            null
                                              ? void 0
                                              : ee.toLocaleString(),
                                            " Emails",
                                          ],
                                        }),
                                        H > 0
                                          ? r.jsxs("span", {
                                              className:
                                                "inline-flex items-center px-2.5 py-1 rounded-full bg-yellow-100 text-yellow-800 text-xs sm:text-sm font-medium",
                                              children: [
                                                r.jsx("i", {
                                                  className:
                                                    "fas fa-clock mr-1",
                                                }),
                                                H,
                                                "% Remaining",
                                              ],
                                            })
                                          : r.jsxs("span", {
                                              className:
                                                "inline-flex items-center px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 text-xs sm:text-sm font-medium",
                                              children: [
                                                r.jsx("i", {
                                                  className:
                                                    "fas fa-check-circle mr-1",
                                                }),
                                                "Fully Allocated",
                                              ],
                                            }),
                                        r.jsx(oe, {
                                          status: S.campaign_status,
                                        }),
                                      ],
                                    }),
                                  ],
                                }),
                                r.jsxs("div", {
                                  className:
                                    "flex flex-row flex-wrap gap-2 items-center mt-2 sm:mt-0",
                                  children: [
                                    r.jsx("button", {
                                      onClick: () => Z(S.campaign_id),
                                      className:
                                        "text-gray-500 hover:text-gray-700 px-2 py-1 rounded-lg",
                                      children: r.jsx("i", {
                                        className: `fas ${
                                          y[S.campaign_id]
                                            ? "fa-chevron-up"
                                            : "fa-chevron-down"
                                        } text-sm`,
                                      }),
                                    }),
                                    r.jsxs("button", {
                                      onClick: () => je(S.campaign_id),
                                      className:
                                        "px-3 sm:px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs sm:text-sm font-medium transition-colors",
                                      children: [
                                        r.jsx("i", {
                                          className: "fas fa-magic mr-1",
                                        }),
                                        " Auto-Distribute",
                                      ],
                                    }),
                                    S.campaign_status === "running"
                                      ? r.jsxs("button", {
                                          onClick: () => Me(S.campaign_id),
                                          className:
                                            "px-3 sm:px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-xs sm:text-sm font-medium",
                                          children: [
                                            r.jsx("i", {
                                              className: "fas fa-pause mr-1",
                                            }),
                                            " Pause",
                                          ],
                                        })
                                      : S.campaign_status === "completed"
                                      ? r.jsxs("span", {
                                          className:
                                            "px-3 sm:px-4 py-2 bg-gray-200 text-gray-600 rounded-lg text-xs sm:text-sm font-medium",
                                          children: [
                                            r.jsx("i", {
                                              className:
                                                "fas fa-check-circle mr-1",
                                            }),
                                            " Completed",
                                          ],
                                        })
                                      : r.jsxs("button", {
                                          onClick: () => Ue(S.campaign_id),
                                          className:
                                            "px-3 sm:px-4 py-2 bg-green-500 hover:bg-green-700 text-white rounded-lg text-xs sm:text-sm font-medium",
                                          children: [
                                            r.jsx("i", {
                                              className:
                                                "fas fa-paper-plane mr-1",
                                            }),
                                            " Send",
                                          ],
                                        }),
                                    S.failed_emails > 0 &&
                                      S.campaign_status !== "completed" &&
                                      r.jsxs("button", {
                                        onClick: () => F(S.campaign_id),
                                        className:
                                          "px-3 sm:px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs sm:text-sm font-medium",
                                        children: [
                                          r.jsx("i", {
                                            className: "fas fa-redo mr-1",
                                          }),
                                          " Retry Failed",
                                        ],
                                      }),
                                  ],
                                }),
                              ],
                            }),
                            y[S.campaign_id] &&
                              r.jsxs("div", {
                                className: "mt-6",
                                children: [
                                  r.jsx("div", {
                                    className: "space-y-3 mb-4",
                                    children: $.map((g, M) => {
                                      o.find(
                                        (le) =>
                                          String(le.id) === String(g.smtp_id)
                                      );
                                      const J = Number(S.valid_emails) || 0,
                                        K = Number(g.percentage) || 0,
                                        P = Math.floor(J * (K / 100));
                                      return r.jsxs(
                                        "div",
                                        {
                                          className:
                                            "flex items-center space-x-4 p-3 bg-gray-50 rounded-lg",
                                          children: [
                                            r.jsx("select", {
                                              value: g.smtp_id,
                                              onChange: (le) =>
                                                re(
                                                  S.campaign_id,
                                                  M,
                                                  "smtp_id",
                                                  le.target.value
                                                ),
                                              className:
                                                "flex-1 min-w-0 text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500",
                                              children:
                                                o.length === 0
                                                  ? r.jsx("option", {
                                                      value: "",
                                                      children:
                                                        "No SMTP servers available",
                                                    })
                                                  : o.map((le) =>
                                                      r.jsxs(
                                                        "option",
                                                        {
                                                          value: le.id,
                                                          children: [
                                                            le.name,
                                                            " (",
                                                            le.daily_limit.toLocaleString(),
                                                            "/day)",
                                                          ],
                                                        },
                                                        le.id
                                                      )
                                                    ),
                                            }),
                                            r.jsxs("div", {
                                              className: "relative w-32",
                                              children: [
                                                r.jsx("input", {
                                                  type: "number",
                                                  min: "1",
                                                  max: 100,
                                                  step: "0.1",
                                                  value: g.percentage,
                                                  onChange: (le) => {
                                                    let te = le.target.value;
                                                    te === ""
                                                      ? (te = "")
                                                      : parseFloat(te) < 1
                                                      ? (te = 1)
                                                      : parseFloat(te) > 100 &&
                                                        (te = 100),
                                                      re(
                                                        S.campaign_id,
                                                        M,
                                                        "percentage",
                                                        te
                                                      );
                                                  },
                                                  className:
                                                    "text-sm border border-gray-300 rounded-lg px-3 py-2 pr-8 w-full focus:ring-blue-500 focus:border-blue-500",
                                                }),
                                                r.jsx("span", {
                                                  className:
                                                    "absolute right-3 top-1/2 transform -translate-y-1/2 text-xs text-gray-500",
                                                  children: "%",
                                                }),
                                              ],
                                            }),
                                            r.jsxs("div", {
                                              className:
                                                "flex items-center space-x-2",
                                              children: [
                                                r.jsxs("span", {
                                                  className:
                                                    "email-count bg-gray-200 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded-full",
                                                  children: [
                                                    "~",
                                                    P.toLocaleString(),
                                                    " emails",
                                                  ],
                                                }),
                                                r.jsx("button", {
                                                  type: "button",
                                                  className:
                                                    "text-red-500 hover:text-red-700",
                                                  onClick: () =>
                                                    W(S.campaign_id, M),
                                                  children: r.jsx("i", {
                                                    className:
                                                      "fas fa-trash-alt",
                                                  }),
                                                }),
                                              ],
                                            }),
                                          ],
                                        },
                                        M
                                      );
                                    }),
                                  }),
                                  r.jsxs("div", {
                                    className:
                                      "flex justify-between items-center",
                                    children: [
                                      r.jsxs("button", {
                                        type: "button",
                                        disabled: H <= 0,
                                        onClick: () => Q(S.campaign_id),
                                        className: `px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50
                            ${H <= 0 ? "opacity-50 cursor-not-allowed" : ""}
                          `,
                                        children: [
                                          r.jsx("i", {
                                            className: "fas fa-plus mr-1",
                                          }),
                                          " Add SMTP Server",
                                        ],
                                      }),
                                      r.jsxs("div", {
                                        className: "flex space-x-3",
                                        children: [
                                          r.jsx("span", {
                                            className: "text-sm text-gray-600",
                                            children:
                                              H > 0
                                                ? r.jsxs(r.Fragment, {
                                                    children: [
                                                      r.jsx("i", {
                                                        className:
                                                          "fas fa-info-circle text-blue-500 mr-1",
                                                      }),
                                                      H,
                                                      "% remaining to allocate",
                                                    ],
                                                  })
                                                : r.jsxs(r.Fragment, {
                                                    children: [
                                                      r.jsx("i", {
                                                        className:
                                                          "fas fa-check-circle text-green-500 mr-1",
                                                      }),
                                                      "Fully allocated",
                                                    ],
                                                  }),
                                          }),
                                          r.jsxs("button", {
                                            type: "button",
                                            onClick: () => ce(S.campaign_id),
                                            className:
                                              "px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium",
                                            children: [
                                              r.jsx("i", {
                                                className: "fas fa-save mr-1",
                                              }),
                                              " Save Distribution",
                                            ],
                                          }),
                                        ],
                                      }),
                                    ],
                                  }),
                                ],
                              }),
                          ],
                        }),
                      },
                      S.campaign_id
                    )
                  );
                }),
              }),
              s.length > 0 &&
                r.jsxs("div", {
                  className:
                    "flex flex-col items-center justify-center mt-6 px-1 gap-2",
                  children: [
                    r.jsxs("div", {
                      className: "text-xs sm:text-sm text-gray-500 mb-2",
                      children: [
                        "Showing",
                        " ",
                        r.jsx("span", {
                          className: "font-medium",
                          children: (w.page - 1) * w.rowsPerPage + 1,
                        }),
                        " ",
                        "to",
                        " ",
                        r.jsx("span", {
                          className: "font-medium",
                          children: Math.min(w.page * w.rowsPerPage, w.total),
                        }),
                        " ",
                        "of ",
                        r.jsx("span", {
                          className: "font-medium",
                          children: w.total,
                        }),
                        " ",
                        "campaigns",
                      ],
                    }),
                    r.jsxs("div", {
                      className: "flex flex-wrap items-center gap-2",
                      children: [
                        r.jsx("button", {
                          onClick: () => C((S) => ({ ...S, page: 1 })),
                          disabled: w.page === 1,
                          className:
                            "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors",
                          children: r.jsx("svg", {
                            className: "w-5 h-5 text-gray-500",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24",
                            children: r.jsx("path", {
                              strokeLinecap: "round",
                              strokeLinejoin: "round",
                              strokeWidth: "2",
                              d: "M11 19l-7-7 7-7m8 14l-7-7 7-7",
                            }),
                          }),
                        }),
                        r.jsx("button", {
                          onClick: () => C((S) => ({ ...S, page: S.page - 1 })),
                          disabled: w.page === 1,
                          className:
                            "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors",
                          children: r.jsx("svg", {
                            className: "w-5 h-5 text-gray-500",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24",
                            children: r.jsx("path", {
                              strokeLinecap: "round",
                              strokeLinejoin: "round",
                              strokeWidth: "2",
                              d: "M15 19l-7-7 7-7",
                            }),
                          }),
                        }),
                        r.jsxs("span", {
                          className:
                            "text-xs sm:text-sm font-medium text-gray-700",
                          children: ["Page ", w.page, " of ", D],
                        }),
                        r.jsx("button", {
                          onClick: () =>
                            C((S) => ({ ...S, page: Math.min(D, S.page + 1) })),
                          disabled: w.page >= D,
                          className:
                            "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors",
                          children: r.jsx("svg", {
                            className: "w-5 h-5 text-gray-500",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24",
                            children: r.jsx("path", {
                              strokeLinecap: "round",
                              strokeLinejoin: "round",
                              strokeWidth: "2",
                              d: "M9 5l7 7-7 7",
                            }),
                          }),
                        }),
                        r.jsx("button", {
                          onClick: () => C((S) => ({ ...S, page: D })),
                          disabled: w.page >= D,
                          className:
                            "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors",
                          children: r.jsx("svg", {
                            className: "w-5 h-5 text-gray-500",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24",
                            children: r.jsx("path", {
                              strokeLinecap: "round",
                              strokeLinejoin: "round",
                              strokeWidth: "2",
                              d: "M13 5l7 7-7 7M5 5l7 7-7 7",
                            }),
                          }),
                        }),
                        r.jsx("select", {
                          value: w.rowsPerPage,
                          onChange: (S) =>
                            C((H) => ({
                              ...H,
                              rowsPerPage: Number(S.target.value),
                              page: 1,
                            })),
                          className:
                            "border p-2 rounded-lg text-xs sm:text-sm bg-white focus:ring-blue-500 focus:border-blue-500 transition-colors",
                          children: [10, 25, 50, 100].map((S) =>
                            r.jsx("option", { value: S, children: S }, S)
                          ),
                        }),
                      ],
                    }),
                  ],
                }),
            ],
          }),
        });
  },
  _b = {
    pending: "bg-yellow-500",
    running: "bg-blue-600",
    paused: "bg-gray-500",
    completed: "bg-green-600",
    failed: "bg-red-600",
  },
  Ab = 10,
  Rb = () => {
    const [s, u] = T.useState([]),
      [o, c] = T.useState(!0),
      [f, m] = T.useState(null),
      x = T.useRef(!0),
      [b, y] = T.useState({ page: 1, rowsPerPage: Ab, total: 0 }),
      p = async () => {
        x.current && c(!0);
        try {
          const L = await (
            await fetch(
              "/backend/routes/api.php/api/monitor/campaigns"
            )
          ).json();
          u(Array.isArray(L) ? L : []),
            y((w) => ({ ...w, total: Array.isArray(L) ? L.length : 0 }));
        } catch {
          m({ type: "error", text: "Failed to load campaigns." });
        }
        c(!1), (x.current = !1);
      };
    T.useEffect(() => {
      p();
      const N = setInterval(p, 5e3);
      return () => clearInterval(N);
    }, []);
    const v = Math.ceil(b.total / b.rowsPerPage),
      R = s.slice((b.page - 1) * b.rowsPerPage, b.page * b.rowsPerPage);
    return (
      T.useEffect(() => {
        b.page > v && v > 0 && y((N) => ({ ...N, page: 1 }));
      }, [s, v]),
      r.jsxs("div", {
        className: "container mx-auto px-4 py-8 max-w-7xl",
        children: [
          r.jsxs("h1", {
            className:
              "text-2xl font-bold text-gray-800 mb-6 flex items-center",
            children: [
              r.jsx("i", { className: "fas fa-chart-line mr-2 text-blue-600" }),
              "Campaign Monitor",
            ],
          }),
          f &&
            r.jsxs("div", {
              className:
                "bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md flex items-start",
              children: [
                r.jsx("div", {
                  className: "ml-3",
                  children: r.jsx("p", {
                    className: "text-sm font-medium",
                    children: f.text,
                  }),
                }),
                r.jsx("div", {
                  className: "ml-auto pl-3",
                  children: r.jsx("button", {
                    onClick: () => m(null),
                    className: "text-gray-500 hover:text-gray-700",
                    children: r.jsx("i", { className: "fas fa-times" }),
                  }),
                }),
              ],
            }),
          r.jsx("div", {
            className: "bg-white rounded-lg shadow overflow-hidden",
            children: r.jsx("div", {
              className: "overflow-x-auto",
              children: r.jsxs("table", {
                className: "min-w-full divide-y divide-gray-200",
                children: [
                  r.jsx("thead", {
                    className: "bg-gray-50",
                    children: r.jsxs("tr", {
                      children: [
                        r.jsx("th", {
                          className:
                            "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                          children: "ID",
                        }),
                        r.jsx("th", {
                          className:
                            "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                          children: "Description",
                        }),
                        r.jsx("th", {
                          className:
                            "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                          children: "Status",
                        }),
                        r.jsx("th", {
                          className:
                            "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                          children: "Progress",
                        }),
                        r.jsx("th", {
                          className:
                            "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                          children: "Emails",
                        }),
                      ],
                    }),
                  }),
                  r.jsx("tbody", {
                    className: "bg-white divide-y divide-gray-200",
                    children: o
                      ? r.jsx("tr", {
                          children: r.jsx("td", {
                            colSpan: 5,
                            className:
                              "px-6 py-4 text-center text-sm text-gray-500",
                            children: "Loading...",
                          }),
                        })
                      : R.length === 0
                      ? r.jsx("tr", {
                          children: r.jsx("td", {
                            colSpan: 5,
                            className:
                              "px-6 py-4 text-center text-sm text-gray-500",
                            children: "No campaigns found.",
                          }),
                        })
                      : R.map((N) => {
                          const L = Math.max(N.total_emails || 0, 1),
                            w = Math.min(N.sent_emails || 0, L),
                            C = Math.round((w / L) * 100),
                            D = (N.campaign_status || "pending").toLowerCase();
                          return r.jsxs(
                            "tr",
                            {
                              className: "hover:bg-gray-50",
                              children: [
                                r.jsx("td", {
                                  className:
                                    "px-6 py-4 whitespace-nowrap text-sm text-gray-500",
                                  children: N.campaign_id,
                                }),
                                r.jsx("td", {
                                  className: "px-6 py-4",
                                  children: r.jsx("div", {
                                    className:
                                      "text-sm font-medium text-gray-900",
                                    children: N.description,
                                  }),
                                }),
                                r.jsx("td", {
                                  className: "px-6 py-4 whitespace-nowrap",
                                  children: r.jsx("span", {
                                    className: `status-badge px-2 py-1 rounded text-xs font-semibold ${
                                      _b[D] || "bg-gray-400"
                                    } text-white`,
                                    children:
                                      N.campaign_status || "Not started",
                                  }),
                                }),
                                r.jsxs("td", {
                                  className: "px-6 py-4 whitespace-nowrap",
                                  children: [
                                    r.jsx("div", {
                                      className:
                                        "progress-bar h-5 bg-gray-200 rounded",
                                      children: r.jsx("div", {
                                        className:
                                          "progress-fill bg-blue-600 h-5 rounded",
                                        style: { width: `${C}%` },
                                      }),
                                    }),
                                    r.jsxs("div", {
                                      className: "text-xs text-gray-500 mt-1",
                                      children: [
                                        C,
                                        "% (",
                                        N.sent_emails || 0,
                                        "/",
                                        N.total_emails || 0,
                                        ")",
                                      ],
                                    }),
                                  ],
                                }),
                                r.jsxs("td", {
                                  className:
                                    "px-6 py-4 whitespace-nowrap text-sm text-gray-500",
                                  children: [
                                    r.jsxs("div", {
                                      children: [
                                        "Total: ",
                                        N.total_emails || 0,
                                      ],
                                    }),
                                    r.jsxs("div", {
                                      children: [
                                        "Pending: ",
                                        N.pending_emails || 0,
                                      ],
                                    }),
                                    r.jsxs("div", {
                                      children: ["Sent: ", N.sent_emails || 0],
                                    }),
                                    r.jsxs("div", {
                                      children: [
                                        "Failed: ",
                                        N.failed_emails || 0,
                                      ],
                                    }),
                                  ],
                                }),
                              ],
                            },
                            N.campaign_id
                          );
                        }),
                  }),
                ],
              }),
            }),
          }),
          b.total > 0 &&
            r.jsxs("div", {
              className:
                "flex flex-col items-center justify-center mt-6 px-1 gap-2",
              children: [
                r.jsx("div", {
                  className: "flex items-center gap-4 mb-2",
                  children: r.jsxs("div", {
                    className: "text-xs sm:text-sm text-gray-500",
                    children: [
                      "Showing",
                      " ",
                      r.jsx("span", {
                        className: "font-medium",
                        children: (b.page - 1) * b.rowsPerPage + 1,
                      }),
                      " ",
                      "to",
                      " ",
                      r.jsx("span", {
                        className: "font-medium",
                        children: Math.min(b.page * b.rowsPerPage, b.total),
                      }),
                      " ",
                      "of ",
                      r.jsx("span", {
                        className: "font-medium",
                        children: b.total,
                      }),
                      " ",
                      "campaigns",
                    ],
                  }),
                }),
                r.jsxs("div", {
                  className: "flex flex-wrap items-center gap-2",
                  children: [
                    r.jsx("button", {
                      onClick: () => y((N) => ({ ...N, page: 1 })),
                      disabled: b.page === 1,
                      className:
                        "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors",
                      children: r.jsx("svg", {
                        className: "w-5 h-5 text-gray-500",
                        fill: "none",
                        stroke: "currentColor",
                        viewBox: "0 0 24 24",
                        children: r.jsx("path", {
                          strokeLinecap: "round",
                          strokeLinejoin: "round",
                          strokeWidth: "2",
                          d: "M11 19l-7-7 7-7m8 14l-7-7 7-7",
                        }),
                      }),
                    }),
                    r.jsx("button", {
                      onClick: () => y((N) => ({ ...N, page: N.page - 1 })),
                      disabled: b.page === 1,
                      className:
                        "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors",
                      children: r.jsx("svg", {
                        className: "w-5 h-5 text-gray-500",
                        fill: "none",
                        stroke: "currentColor",
                        viewBox: "0 0 24 24",
                        children: r.jsx("path", {
                          strokeLinecap: "round",
                          strokeLinejoin: "round",
                          strokeWidth: "2",
                          d: "M15 19l-7-7 7-7",
                        }),
                      }),
                    }),
                    r.jsxs("span", {
                      className: "text-xs sm:text-sm font-medium text-gray-700",
                      children: ["Page ", b.page, " of ", v],
                    }),
                    r.jsx("button", {
                      onClick: () =>
                        y((N) => ({ ...N, page: Math.min(v, N.page + 1) })),
                      disabled: b.page >= v,
                      className:
                        "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors",
                      children: r.jsx("svg", {
                        className: "w-5 h-5 text-gray-500",
                        fill: "none",
                        stroke: "currentColor",
                        viewBox: "0 0 24 24",
                        children: r.jsx("path", {
                          strokeLinecap: "round",
                          strokeLinejoin: "round",
                          strokeWidth: "2",
                          d: "M9 5l7 7-7 7",
                        }),
                      }),
                    }),
                    r.jsx("button", {
                      onClick: () => y((N) => ({ ...N, page: v })),
                      disabled: b.page >= v,
                      className:
                        "p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors",
                      children: r.jsx("svg", {
                        className: "w-5 h-5 text-gray-500",
                        fill: "none",
                        stroke: "currentColor",
                        viewBox: "0 0 24 24",
                        children: r.jsx("path", {
                          strokeLinecap: "round",
                          strokeLinejoin: "round",
                          strokeWidth: "2",
                          d: "M13 5l7 7-7 7M5 5l7 7-7 7",
                        }),
                      }),
                    }),
                    r.jsx("div", {
                      className: "flex items-center gap-1 ml-4",
                      children: r.jsx("select", {
                        id: "rowsPerPage",
                        value: b.rowsPerPage,
                        onChange: (N) => {
                          y((L) => ({
                            ...L,
                            rowsPerPage: Number(N.target.value),
                            page: 1,
                          }));
                        },
                        className:
                          "border border-gray-300 rounded px-2 py-2 text-xs sm:text-sm",
                        children: [10, 25, 50, 100].map((N) =>
                          r.jsx("option", { value: N, children: N }, N)
                        ),
                      }),
                    }),
                  ],
                }),
              ],
            }),
        ],
      })
    );
  },
  Cb = () =>
    r.jsx("div", {
      className: "text-3xl font-bold underline",
      children: "Received Response",
    }),
  Wm = [
    { to: "/", icon: "fa-check-circle", label: "Verification" },
    { to: "/smtp", icon: "fa-server", label: "SMTP" },
    { to: "/workers", icon: "fa-users-cog", label: "Workers" },
    { to: "/campaigns", icon: "fa-bullhorn", label: "Campaigns" },
    { to: "/master", icon: "fa-crown", label: "Master" },
  ],
  Pm = [
    { to: "/monitor/email-sent", icon: "fa-paper-plane", label: "Email Sent" },
    {
      to: "/monitor/received-response",
      icon: "fa-reply",
      label: "Received Response",
    },
  ];
function Ob() {
  const [s, u] = T.useState(!1),
    [o, c] = T.useState(!1),
    [f, m] = T.useState(!1);
  return r.jsxs("nav", {
    className: "fixed top-0 left-0 right-0 bg-white shadow-sm z-50",
    children: [
      r.jsx("div", {
        className: "max-w-7xl mx-auto px-4 sm:px-6 lg:px-8",
        children: r.jsxs("div", {
          className: "flex justify-between h-16 ",
          children: [
            r.jsx("div", {
              className: "flex items-center",
              children: r.jsxs("div", {
                className: "flex-shrink-0 flex items-center",
                children: [
                  r.jsx("i", {
                    className: "fas fa-envelope text-blue-600 mr-2",
                  }),
                  r.jsx("span", {
                    className: "text-gray-800 font-semibold",
                    children: "Email System",
                  }),
                ],
              }),
            }),
            r.jsxs("div", {
              className: "hidden md:flex items-center space-x-1",
              children: [
                Wm.map((x) =>
                  r.jsxs(
                    Zn,
                    {
                      to: x.to,
                      className: ({ isActive: b }) =>
                        `${
                          b
                            ? "bg-blue-50 text-blue-600"
                            : "text-gray-600 hover:bg-blue-50 hover:text-blue-600"
                        } px-3 py-2 rounded-md text-sm font-medium flex items-center`,
                      children: [
                        r.jsx("i", { className: `fas ${x.icon} mr-2` }),
                        x.label,
                      ],
                    },
                    x.to
                  )
                ),
                r.jsxs("div", {
                  className: "relative",
                  children: [
                    r.jsxs("button", {
                      onClick: () => c((x) => !x),
                      onBlur: () => setTimeout(() => c(!1), 150),
                      className: `${
                        window.location.pathname.startsWith("/monitor/")
                          ? "bg-blue-50 text-blue-600"
                          : "text-gray-600 hover:bg-blue-50 hover:text-blue-600"
                      } px-3 py-2 rounded-md text-sm font-medium flex items-center`,
                      type: "button",
                      children: [
                        r.jsx("i", { className: "fas fa-chart-line mr-2" }),
                        " Monitor",
                        r.jsx("i", {
                          className: "fas fa-chevron-down ml-1 text-xs",
                        }),
                      ],
                    }),
                    o &&
                      r.jsx("div", {
                        className:
                          "origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50",
                        children: r.jsx("div", {
                          className: "py-1",
                          children: Pm.map((x) =>
                            r.jsxs(
                              Zn,
                              {
                                to: x.to,
                                className: ({ isActive: b }) =>
                                  `block px-4 py-2 text-sm flex items-center ${
                                    b
                                      ? "bg-blue-50 text-blue-600"
                                      : "text-gray-700 hover:bg-blue-50 hover:text-blue-600"
                                  }`,
                                children: [
                                  r.jsx("i", {
                                    className: `fas ${x.icon} mr-2 w-4 text-center`,
                                  }),
                                  x.label,
                                ],
                              },
                              x.to
                            )
                          ),
                        }),
                      }),
                  ],
                }),
              ],
            }),
            r.jsx("div", {
              className: "-mr-2 flex items-center md:hidden",
              children: r.jsxs("button", {
                onClick: () => u((x) => !x),
                type: "button",
                className:
                  "inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none",
                children: [
                  r.jsx("span", {
                    className: "sr-only",
                    children: "Open main menu",
                  }),
                  r.jsx("i", {
                    className: `fas ${s ? "fa-times" : "fa-bars"}`,
                  }),
                ],
              }),
            }),
          ],
        }),
      }),
      s &&
        r.jsx("div", {
          className: "md:hidden bg-white border-t border-gray-200 shadow",
          children: r.jsxs("div", {
            className: "pt-2 pb-3 space-y-1",
            children: [
              Wm.map((x) =>
                r.jsxs(
                  Zn,
                  {
                    to: x.to,
                    className: ({ isActive: b }) =>
                      `block pl-3 pr-4 py-2 border-l-4 text-base font-medium flex items-center ${
                        b
                          ? "bg-blue-50 text-blue-600 border-blue-500"
                          : "text-gray-600 hover:bg-blue-50 hover:text-blue-600 border-transparent"
                      }`,
                    onClick: () => u(!1),
                    children: [
                      r.jsx("i", { className: `fas ${x.icon} mr-2` }),
                      x.label,
                    ],
                  },
                  x.to
                )
              ),
              r.jsxs("div", {
                className: "border-t border-gray-200 pt-2",
                children: [
                  r.jsxs("button", {
                    onClick: () => m((x) => !x),
                    className: `w-full pl-3 pr-4 py-2 border-l-4 text-base font-medium flex justify-between items-center ${
                      window.location.pathname.startsWith("/monitor/")
                        ? "bg-blue-50 text-blue-600 border-blue-500"
                        : "text-gray-600 hover:bg-blue-50 hover:text-blue-600 border-transparent"
                    }`,
                    children: [
                      r.jsxs("div", {
                        className: "flex items-center",
                        children: [
                          r.jsx("i", { className: "fas fa-chart-line mr-2" }),
                          " Monitor",
                        ],
                      }),
                      r.jsx("i", {
                        className: `fas fa-chevron-right transition-transform duration-200 ${
                          f ? "transform rotate-90" : ""
                        }`,
                      }),
                    ],
                  }),
                  f &&
                    r.jsx("div", {
                      className: "pl-8",
                      children: Pm.map((x) =>
                        r.jsxs(
                          Zn,
                          {
                            to: x.to,
                            className: ({ isActive: b }) =>
                              `block pl-3 pr-4 py-2 text-base font-medium flex items-center ${
                                b
                                  ? "bg-blue-50 text-blue-600"
                                  : "text-gray-600 hover:bg-blue-50 hover:text-blue-600"
                              }`,
                            onClick: () => u(!1),
                            children: [
                              r.jsx("i", { className: `fas ${x.icon} mr-2` }),
                              x.label,
                            ],
                          },
                          x.to
                        )
                      ),
                    }),
                ],
              }),
            ],
          }),
        }),
    ],
  });
}
const Mb = () => {
    const [s, u] = T.useState(0),
      [o, c] = T.useState(!1);
    return (
      T.useEffect(() => {
        let f = null;
        const m = async () => {
          try {
            const b = await (
              await fetch(
                "/backend/includes/progress.php"
              )
            ).json();
            b && typeof b.percent == "number" && b.total > 0 && b.percent < 100
              ? (u(b.percent), c(!0))
              : (c(!1), u(0));
          } catch {
            c(!1), u(0);
          }
        };
        return m(), (f = setInterval(m, 2e3)), () => clearInterval(f);
      }, []),
      o
        ? r.jsx("div", {
            className: "fixed top-0 left-0 w-full z-50",
            children: r.jsx("div", {
              className: "h-1 bg-blue-500 transition-all duration-500",
              style: { width: `${s}%` },
            }),
          })
        : null
    );
  },
  bi = "/backend/routes/api.php/api/workers",
  vi = { workername: "", ip: "" },
  oc = ({ status: s, onClose: u }) =>
    s &&
    r.jsxs("div", {
      className: `
        fixed top-6 left-1/2 transform -translate-x-1/2 z-50
        px-6 py-3 rounded-xl shadow text-base font-semibold
        flex items-center gap-3
        transition-all duration-300
        backdrop-blur-md
        ${
          s.type === "error"
            ? "bg-red-200/60 border border-red-400 text-red-800"
            : "bg-green-200/60 border border-green-400 text-green-800"
        }
      `,
      style: {
        minWidth: 250,
        maxWidth: 400,
        boxShadow: "0 8px 32px 0 rgba(0, 0, 0, 0.23)",
        background:
          s.type === "error"
            ? "rgba(255, 0, 0, 0.29)"
            : "rgba(0, 200, 83, 0.29)",
        borderRadius: "16px",
        backdropFilter: "blur(8px)",
        WebkitBackdropFilter: "blur(8px)",
      },
      role: "alert",
      children: [
        r.jsx("i", {
          className: `fas text-lg ${
            s.type === "error"
              ? "fa-exclamation-circle text-red-500"
              : "fa-check-circle text-green-500"
          }`,
        }),
        r.jsx("span", { className: "flex-1", children: s.message }),
        r.jsx("button", {
          onClick: u,
          className:
            "ml-2 text-gray-500 hover:text-gray-700 focus:outline-none",
          "aria-label": "Close",
          children: r.jsx("i", { className: "fas fa-times" }),
        }),
      ],
    });
function Im(s) {
  const u =
      /^(25[0-5]|2[0-4]\d|1\d{2}|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d{2}|[1-9]?\d)){3}$/,
    o = /^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/;
  return u.test(s) || o.test(s);
}
const Db = () => {
  const [s, u] = T.useState([]),
    [o, c] = T.useState(!0),
    [f, m] = T.useState(!1),
    [x, b] = T.useState(!1),
    [y, p] = T.useState(vi),
    [v, R] = T.useState(null),
    [N, L] = T.useState(null),
    w = async () => {
      c(!0);
      try {
        const Q = await (await fetch(bi)).json();
        Array.isArray(Q) ? u(Q) : Array.isArray(Q.data) ? u(Q.data) : u([]);
      } catch {
        L({ type: "error", message: "Failed to load workers." }), u([]);
      }
      c(!1);
    };
  T.useEffect(() => {
    w();
  }, []);
  const C = (q) => {
      const { name: Q, value: W } = q.target;
      p((re) => ({ ...re, [Q]: W }));
    },
    D = async (q) => {
      if ((q.preventDefault(), !Im(y.ip))) {
        L({ type: "error", message: "Invalid IP address format." });
        return;
      }
      try {
        const Q = await fetch(bi, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(y),
          }),
          W = await Q.json();
        Q.ok || W.success
          ? (L({
              type: "success",
              message: W.message || "Worker added successfully!",
            }),
            m(!1),
            p(vi),
            w())
          : L({
              type: "error",
              message: W.error || W.message || "Failed to add worker.",
            });
      } catch {
        L({ type: "error", message: "Failed to add worker." });
      }
    },
    Y = (q) => {
      R(q.id), p({ workername: q.workername, ip: q.ip }), b(!0);
    },
    Z = async (q) => {
      if ((q.preventDefault(), !Im(y.ip))) {
        L({ type: "error", message: "Invalid IP address format." });
        return;
      }
      try {
        const Q = await fetch(bi, {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: v, ...y }),
          }),
          W = await Q.json();
        Q.ok || W.success
          ? (L({
              type: "success",
              message: W.message || "Worker updated successfully!",
            }),
            b(!1),
            p(vi),
            R(null),
            w())
          : L({
              type: "error",
              message: W.error || W.message || "Failed to update worker.",
            });
      } catch {
        L({ type: "error", message: "Failed to update worker." });
      }
    },
    U = async (q) => {
      if (window.confirm("Are you sure you want to delete this worker?"))
        try {
          const Q = await fetch(bi, {
              method: "DELETE",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ id: q }),
            }),
            W = await Q.json();
          Q.ok || W.success
            ? (L({
                type: "success",
                message: W.message || "Worker deleted successfully!",
              }),
              w())
            : L({
                type: "error",
                message: W.error || W.message || "Failed to delete worker.",
              });
        } catch {
          L({ type: "error", message: "Failed to delete worker." });
        }
    };
  return (
    T.useEffect(() => {
      if (N) {
        const q = setTimeout(() => L(null), 3e3);
        return () => clearTimeout(q);
      }
    }, [N]),
    r.jsxs("main", {
      className: "max-w-7xl mx-auto px-4 mt-14 sm:px-6 py-6",
      children: [
        r.jsx(oc, { status: N, onClose: () => L(null) }),
        r.jsxs("div", {
          className: "flex justify-between items-center mb-6",
          children: [
            r.jsxs("h1", {
              className: "text-2xl font-bold text-gray-900 flex items-center",
              children: [
                r.jsx("i", { className: "fas fa-users mr-3 text-blue-600" }),
                "Workers",
              ],
            }),
            r.jsxs("button", {
              onClick: () => {
                p(vi), m(!0);
              },
              className:
                "inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500",
              children: [
                r.jsx("i", { className: "fas fa-plus mr-2" }),
                " Add Worker",
              ],
            }),
          ],
        }),
        r.jsx("div", {
          className: "card overflow-hidden bg-white rounded-xl shadow",
          children: r.jsx("div", {
            className: "overflow-x-auto",
            children: r.jsxs("table", {
              className: "min-w-full divide-y divide-gray-200",
              children: [
                r.jsx("thead", {
                  className: "bg-gray-50",
                  children: r.jsxs("tr", {
                    children: [
                      r.jsx("th", {
                        className:
                          "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "ID",
                      }),
                      r.jsx("th", {
                        className:
                          "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "Worker Name",
                      }),
                      r.jsx("th", {
                        className:
                          "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "IP Address",
                      }),
                      r.jsx("th", {
                        className:
                          "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                        children: "Actions",
                      }),
                    ],
                  }),
                }),
                r.jsx("tbody", {
                  className: "bg-white divide-y divide-gray-200",
                  children: o
                    ? r.jsx("tr", {
                        children: r.jsx("td", {
                          colSpan: 4,
                          className:
                            "px-6 py-4 text-center text-sm text-gray-500",
                          children: "Loading...",
                        }),
                      })
                    : s.length === 0
                    ? r.jsx("tr", {
                        children: r.jsx("td", {
                          colSpan: 4,
                          className:
                            "px-6 py-4 text-center text-sm text-gray-500",
                          children: "No workers found. Add one to get started.",
                        }),
                      })
                    : s.map((q) =>
                        r.jsxs(
                          "tr",
                          {
                            children: [
                              r.jsx("td", {
                                className:
                                  "px-6 py-4 whitespace-nowrap text-sm text-gray-500",
                                children: q.id,
                              }),
                              r.jsx("td", {
                                className: "px-6 py-4 whitespace-nowrap",
                                children: r.jsx("div", {
                                  className:
                                    "text-sm font-medium text-gray-900",
                                  children: q.workername,
                                }),
                              }),
                              r.jsx("td", {
                                className:
                                  "px-6 py-4 whitespace-nowrap text-sm text-gray-500",
                                children: q.ip,
                              }),
                              r.jsxs("td", {
                                className:
                                  "px-6 py-4 whitespace-nowrap text-sm font-medium",
                                children: [
                                  r.jsx("button", {
                                    onClick: () => Y(q),
                                    className:
                                      "text-blue-600 hover:text-blue-900 mr-3",
                                    title: "Edit",
                                    children: r.jsx("i", {
                                      className: "fas fa-edit mr-1",
                                    }),
                                  }),
                                  r.jsx("button", {
                                    onClick: () => U(q.id),
                                    className:
                                      "text-red-600 hover:text-red-900",
                                    title: "Delete",
                                    children: r.jsx("i", {
                                      className: "fas fa-trash mr-1",
                                    }),
                                  }),
                                ],
                              }),
                            ],
                          },
                          q.id
                        )
                      ),
                }),
              ],
            }),
          }),
        }),
        f &&
          r.jsxs("div", {
            className:
              "fixed inset-0 bg-black/30 backdrop-blur-md backdrop-saturate-150 border border-white/20 shadow-xl overflow-y-auto h-full w-full z-50 flex items-center justify-center",
            children: [
              r.jsx(oc, { status: N, onClose: () => L(null) }),
              r.jsxs("div", {
                className:
                  "relative mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white",
                children: [
                  r.jsxs("div", {
                    className: "flex justify-between items-center mb-4",
                    children: [
                      r.jsxs("h3", {
                        className: "text-lg font-medium text-gray-900",
                        children: [
                          r.jsx("i", {
                            className: "fas fa-plus-circle mr-2 text-blue-600",
                          }),
                          "Add New Worker",
                        ],
                      }),
                      r.jsx("button", {
                        onClick: () => m(!1),
                        className: "text-gray-400 hover:text-gray-500",
                        children: r.jsx("i", { className: "fas fa-times" }),
                      }),
                    ],
                  }),
                  r.jsxs("form", {
                    className: "space-y-4",
                    onSubmit: D,
                    children: [
                      r.jsxs("div", {
                        children: [
                          r.jsx("label", {
                            className:
                              "block text-sm font-medium text-gray-700 mb-1",
                            children: "Worker Name",
                          }),
                          r.jsx("input", {
                            type: "text",
                            name: "workername",
                            required: !0,
                            maxLength: 50,
                            className:
                              "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm",
                            placeholder: "Enter worker name",
                            value: y.workername,
                            onChange: C,
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        children: [
                          r.jsx("label", {
                            className:
                              "block text-sm font-medium text-gray-700 mb-1",
                            children: "IP Address",
                          }),
                          r.jsx("input", {
                            type: "text",
                            name: "ip",
                            required: !0,
                            maxLength: 39,
                            className:
                              "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm",
                            placeholder: "Enter IP address",
                            value: y.ip,
                            onChange: C,
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        className: "flex justify-end pt-4 space-x-3",
                        children: [
                          r.jsx("button", {
                            type: "button",
                            onClick: () => m(!1),
                            className:
                              "inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500",
                            children: "Cancel",
                          }),
                          r.jsxs("button", {
                            type: "submit",
                            className:
                              "inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500",
                            children: [
                              r.jsx("i", { className: "fas fa-save mr-2" }),
                              " Save Worker",
                            ],
                          }),
                        ],
                      }),
                    ],
                  }),
                ],
              }),
            ],
          }),
        x &&
          r.jsxs("div", {
            className:
              "fixed inset-0 bg-black/30 backdrop-blur-md backdrop-saturate-150 border border-white/20 shadow-xl overflow-y-auto h-full w-full z-50 flex items-center justify-center",
            children: [
              r.jsx(oc, { status: N, onClose: () => L(null) }),
              r.jsxs("div", {
                className:
                  "relative mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white",
                children: [
                  r.jsxs("div", {
                    className: "flex justify-between items-center mb-4",
                    children: [
                      r.jsxs("h3", {
                        className: "text-lg font-medium text-gray-900",
                        children: [
                          r.jsx("i", {
                            className: "fas fa-edit mr-2 text-blue-600",
                          }),
                          "Edit Worker",
                        ],
                      }),
                      r.jsx("button", {
                        onClick: () => b(!1),
                        className: "text-gray-400 hover:text-gray-500",
                        children: r.jsx("i", { className: "fas fa-times" }),
                      }),
                    ],
                  }),
                  r.jsxs("form", {
                    className: "space-y-4",
                    onSubmit: Z,
                    children: [
                      r.jsxs("div", {
                        children: [
                          r.jsx("label", {
                            className:
                              "block text-sm font-medium text-gray-700 mb-1",
                            children: "Worker Name",
                          }),
                          r.jsx("input", {
                            type: "text",
                            name: "workername",
                            required: !0,
                            maxLength: 50,
                            className:
                              "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm",
                            placeholder: "Enter worker name",
                            value: y.workername,
                            onChange: C,
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        children: [
                          r.jsx("label", {
                            className:
                              "block text-sm font-medium text-gray-700 mb-1",
                            children: "IP Address",
                          }),
                          r.jsx("input", {
                            type: "text",
                            name: "ip",
                            required: !0,
                            maxLength: 39,
                            className:
                              "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm",
                            placeholder: "Enter IP address",
                            value: y.ip,
                            onChange: C,
                          }),
                        ],
                      }),
                      r.jsxs("div", {
                        className: "flex justify-end pt-4 space-x-3",
                        children: [
                          r.jsx("button", {
                            type: "button",
                            onClick: () => b(!1),
                            className:
                              "inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500",
                            children: "Cancel",
                          }),
                          r.jsxs("button", {
                            type: "submit",
                            className:
                              "inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500",
                            children: [
                              r.jsx("i", { className: "fas fa-save mr-2" }),
                              " Update Worker",
                            ],
                          }),
                        ],
                      }),
                    ],
                  }),
                ],
              }),
            ],
          }),
      ],
    })
  );
};
bx.createRoot(document.getElementById("root")).render(
  r.jsxs(Ug, {
    children: [
      r.jsx(Ob, {}),
      r.jsx(Mb, {}),
      r.jsxs(fg, {
        children: [
          r.jsx(Oa, { path: "/", element: r.jsx(Qg, {}) }),
          r.jsx(Oa, { path: "/smtp", element: r.jsx(Kg, {}) }),
          r.jsx(Oa, { path: "/campaigns", element: r.jsx($g, {}) }),
          r.jsx(Oa, { path: "/master", element: r.jsx(Tb, {}) }),
          r.jsx(Oa, { path: "/monitor/email-sent", element: r.jsx(Rb, {}) }),
          r.jsx(Oa, {
            path: "/monitor/received-response",
            element: r.jsx(Cb, {}),
          }),
          r.jsx(Oa, { path: "/workers", element: r.jsx(Db, {}) }),
        ],
      }),
    ],
  })
);
