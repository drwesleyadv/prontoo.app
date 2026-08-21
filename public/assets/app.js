(() => {
  "use strict";
  const d = document,
    w = window;
  const $ = (s, r = d) => r.querySelector(s);
  const $$ = (s, r = d) => Array.from(r.querySelectorAll(s));
  const closest = (t, s) => {
    const e = t && t.nodeType === 1 ? t : t?.parentElement;
    return e && e.closest ? e.closest(s) : null;
  };
  const digits = (v) => String(v || "").replace(/\D+/g, "");
  const esc = (v) =>
    w.CSS && CSS.escape
      ? CSS.escape(v)
      : String(v).replace(/[^a-zA-Z0-9_-]/g, "\\$&");
  const cpf = (v) => {
    const x = digits(v).slice(0, 11);
    if (x.length > 9)
      return x.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, "$1.$2.$3-$4");
    if (x.length > 6) return x.replace(/(\d{3})(\d{3})(\d{0,3})/, "$1.$2.$3");
    if (x.length > 3) return x.replace(/(\d{3})(\d{0,3})/, "$1.$2");
    return x;
  };
  const cpfOk = (v) => {
    const x = digits(v);
    if (x.length !== 11 || /^(\d)\1{10}$/.test(x)) return false;
    for (let t = 9; t < 11; t++) {
      let sum = 0;
      for (let i = 0; i < t; i++) sum += Number(x[i]) * (t + 1 - i);
      const d = ((10 * sum) % 11) % 10;
      if (Number(x[t]) !== d) return false;
    }
    return true;
  };
  const cnpjOk = (v) => {
    const x = digits(v);
    if (x.length !== 14 || /^(\d)\1{13}$/.test(x)) return false;
    const calc = (len) => {
      const w =
        len === 12
          ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
          : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
      let sum = 0;
      for (let i = 0; i < len; i++) sum += Number(x[i]) * w[i];
      const r = sum % 11;
      return r < 2 ? 0 : 11 - r;
    };
    return Number(x[12]) === calc(12) && Number(x[13]) === calc(13);
  };
  const docid = (v) => {
    const x = digits(v).slice(0, 14);
    if (x.length > 11)
      return x.replace(
        /(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2})/,
        "$1.$2.$3/$4-$5",
      );
    return cpf(x);
  };
  const tel = (v) => {
    const x = digits(v).slice(0, 11);
    if (!x) return "";
    if (x.length < 3) return `(${x}`;
    const a = x.slice(0, 2),
      n = x.slice(2);
    if (n.length <= 4) return `(${a}) ${n}`;
    return n.length <= 8
      ? `(${a}) ${n.slice(0, 4)}-${n.slice(4)}`
      : `(${a}) ${n.slice(0, 5)}-${n.slice(5, 9)}`;
  };
  const wait = (t) => {
    const m = Math.floor(t / 60),
      s = String(t % 60).padStart(2, "0");
    return (m ? `${m}min ` : "") + `${s}s`;
  };
  const tzUF = {
    AC: "America/Rio_Branco",
    AM: "America/Manaus",
    RO: "America/Porto_Velho",
    RR: "America/Boa_Vista",
    MT: "America/Cuiaba",
    MS: "America/Campo_Grande",
  };
  const tzEX = {
    "PE|Fernando de Noronha": "America/Noronha",
    "AM|Eirunepé": "America/Eirunepe",
    "AM|Envira": "America/Eirunepe",
    "AM|Guajará": "America/Eirunepe",
    "AM|Ipixuna": "America/Eirunepe",
    "AM|Itamarati": "America/Eirunepe",
    "AM|Jutaí": "America/Eirunepe",
    "AM|Tabatinga": "America/Eirunepe",
    "AM|Benjamin Constant": "America/Eirunepe",
    "AM|Atalaia do Norte": "America/Eirunepe",
  };
  const zone = (uf, c) =>
    tzEX[`${uf || ""}|${c || ""}`] || tzUF[uf] || "America/Sao_Paulo";
  function tabKey(v) {
    v = String(v || "");
    const m = v.match(/patient-panel-([a-z0-9_-]+)/i);
    if (m) return m[1];
    v = v.replace(/^#?/, "").replace(/^tab-/, "");
    if (v.includes("_")) v = v.split("_").pop();
    return v.trim().toLowerCase();
  }
  function bindMask(el, fn, max, mode, auto) {
    if (!el || el.dataset.maskReady) return;
    el.dataset.maskReady = "1";
    el.maxLength = max;
    el.inputMode = mode;
    if (auto && !el.autocomplete) el.autocomplete = auto;
    const run = () => {
      const atEnd = el.selectionStart === el.value.length;
      const value = fn(el.value);
      el.value = value;
      if (atEnd && d.activeElement === el)
        try {
          el.setSelectionRange(value.length, value.length);
        } catch (_) {}
    };
    el.addEventListener("input", run, { passive: true });
    el.addEventListener("blur", run, { passive: true });
    run();
  }
  function isCpfField(i) {
    const n = (i?.name || "").toLowerCase();
    if (!i || n === "cpf_omitted") return false;
    return (
      i.hasAttribute("data-cpf-mask") ||
      i.hasAttribute("data-cpf-validate") ||
      /(^|_)cpf($|_)/.test(n) ||
      i.hasAttribute("data-login-cpf")
    );
  }
  function isDocField(i) {
    return (
      !!i &&
      (i.hasAttribute("data-document-validate") ||
        i.hasAttribute("data-doc-mask") ||
        i.hasAttribute("data-counterparty-document-lookup") ||
        i.hasAttribute("data-counterparty-document-suggest"))
    );
  }
  function initMasks(root = d) {
    $$("input", root)
      .filter(isCpfField)
      .forEach((i) => bindMask(i, cpf, 14, "numeric", "off"));
    $$("input", root)
      .filter(isDocField)
      .forEach((i) => bindMask(i, docid, 18, "numeric", "off"));
    $$("input", root).forEach((i) => {
      const n = (i.name || "").toLowerCase();
      if (
        i.type === "tel" ||
        i.autocomplete === "tel" ||
        i.inputMode === "tel" ||
        n.includes("phone") ||
        n.includes("telefone")
      )
        bindMask(i, tel, 15, "tel", "tel");
    });
  }
  function cpfValidationMessage(i) {
    if (!i || i.disabled || i.readOnly) return "";
    const x = digits(i.value);
    if (!x) return i.required ? "Informe o CPF." : "";
    return cpfOk(x) ? "" : "Informe um CPF válido.";
  }
  function documentValidationMessage(i) {
    if (!i || i.disabled || i.readOnly) return "";
    const x = digits(i.value);
    if (!x) return i.required ? "Informe CPF ou CNPJ válido." : "";
    if (x.length === 11) return cpfOk(x) ? "" : "Informe um CPF válido.";
    if (x.length === 14) return cnpjOk(x) ? "" : "Informe um CNPJ válido.";
    return "Informe CPF ou CNPJ válido.";
  }
  function setValidity(i, msg) {
    try {
      i.setCustomValidity(msg || "");
    } catch (_) {}
  }
  function initCpfValidation(root = d) {
    $$("input", root)
      .filter((i) => isCpfField(i) || isDocField(i))
      .forEach((i) => {
        if (i.dataset.cpfValidationReady) return;
        i.dataset.cpfValidationReady = "1";
        const validate = () => {
          const msg = isCpfField(i)
            ? cpfValidationMessage(i)
            : documentValidationMessage(i);
          setValidity(i, msg);
          return !msg;
        };
        i.addEventListener("input", validate, { passive: true });
        i.addEventListener("change", validate);
        i.addEventListener("blur", () => {
          if (!validate())
            try {
              i.reportValidity();
            } catch (_) {}
        });
        validate();
      });
  }
  function validateCpfFields(form) {
    if (!form || !form.querySelectorAll) return true;
    let first = null;
    $$("input", form)
      .filter((i) => isCpfField(i) || isDocField(i))
      .forEach((i) => {
        const msg = isCpfField(i)
          ? cpfValidationMessage(i)
          : documentValidationMessage(i);
        setValidity(i, msg);
        if (msg && !first) first = i;
      });
    if (first) {
      try {
        first.reportValidity();
      } catch (_) {
        try {
          first.focus();
        } catch (__) {}
      }
      return false;
    }
    return true;
  }
  function tabData(shell) {
    const controls = $$(
      '.patient-tab-nav label,.patient-tab-nav [role="tab"],.patient-tab-nav [data-tab-target]',
      shell,
    );
    const panels = $$(".patient-panel", shell);
    const radios = $$(".patient-tab-radio", shell);
    controls.forEach((c, i) => {
      const from =
        c.getAttribute("for") || c.dataset.tabTarget || c.dataset.tabKey || "";
      const key = tabKey(from) || tabKey(panels[i]?.className) || String(i);
      c.dataset.tabKey = key;
      c.setAttribute("role", "tab");
      c.tabIndex = -1;
    });
    panels.forEach((p, i) => {
      const key =
        tabKey(p.className) || controls[i]?.dataset.tabKey || String(i);
      p.dataset.tabKey = key;
      p.setAttribute("role", "tabpanel");
    });
    radios.forEach((r) => (r.dataset.tabKey = tabKey(r.id)));
    return { controls, panels, radios };
  }
  function activateTabs(shell, target, focus = false) {
    if (!shell) return;
    const data = tabData(shell);
    let key = tabKey(target);
    const keys = data.panels.map((p) => p.dataset.tabKey);
    if (!key || !keys.includes(key)) {
      const checked = data.radios.find((r) => r.checked);
      key = checked?.dataset.tabKey || keys[0] || "";
    }
    shell.dataset.activeTab = key;
    data.radios.forEach((r) => {
      r.checked = r.dataset.tabKey === key;
    });
    data.controls.forEach((c) => {
      const active = c.dataset.tabKey === key;
      c.classList.toggle("is-active", active);
      c.setAttribute("aria-selected", active ? "true" : "false");
      c.tabIndex = active ? 0 : -1;
      if (active && focus)
        try {
          c.focus({ preventScroll: true });
        } catch (_) {
          c.focus();
        }
    });
    data.panels.forEach((p) => {
      const active = p.dataset.tabKey === key;
      p.classList.toggle("is-active", active);
      p.hidden = !active;
      p.setAttribute("aria-hidden", active ? "false" : "true");
    });
  }
  function initTabs(root = d) {
    $$(".patient-tabs-shell", root).forEach((shell) => {
      const nav = $(".patient-tab-nav", shell);
      if (nav) nav.setAttribute("role", "tablist");
      activateTabs(shell, shell.dataset.activeTab || "resumo");
    });
  }
  function openTabFrom(control) {
    const shell = control.closest(".patient-tabs-shell");
    if (!shell) return;
    const key =
      control.dataset.tabKey ||
      tabKey(control.getAttribute("for") || control.dataset.tabTarget);
    activateTabs(shell, key, true);
    const panel = $(".patient-panel.is-active", shell);
    if (panel && w.innerWidth < 760)
      setTimeout(
        () => panel.scrollIntoView({ block: "start", behavior: "smooth" }),
        40,
      );
  }
  function hasDirectForm(details) {
    return Array.from(details.children).some((el) => el.tagName === "FORM");
  }
  function initPanels(root = d) {
    $$("details", root).forEach((x) => {
      if (hasDirectForm(x)) x.classList.add("form-panel");
      if (!x.dataset.panelReady) {
        x.dataset.panelReady = "1";
        x.addEventListener("toggle", () => {
          if (x.open && x.closest(".pagehead-controls--actions"))
            $$("details[open]", x.closest(".pagehead-controls--actions")).forEach((y) => {
              if (y !== x) y.open = false;
            });
          updatePanels();
        });
      }
    });
    $$(".compact .field,.patient-record-form .field", root).forEach((f) => {
      if ($("textarea", f)) f.classList.add("has-textarea");
    });
    updatePanels();
  }
  function updatePanels() {
    const scopes = $$(
      ".pagehead,.pagehead-controls--actions,.card,.patient-panel,.patient-tab-panels,.lead-card",
    );
    scopes.forEach((scope) =>
      scope.classList.toggle("has-open-panel", !!$(".form-panel[open]", scope)),
    );
    $$(".form-panel").forEach((p) => p.classList.toggle("is-open", p.open));
  }
  function closePanel(btn) {
    const form = btn.closest("form");
    if (form) {
      try {
        form.reset();
      } catch (_) {}
      $$('[aria-busy="true"]', form).forEach((x) =>
        x.removeAttribute("aria-busy"),
      );
      $$("[data-omit-field]", form).forEach((x) =>
        x.dispatchEvent(new Event("change", { bubbles: true })),
      );
    }
    const details = btn.closest("details");
    if (details) {
      details.open = false;
      details.classList.remove("is-open");
      $("summary", details)?.focus({ preventScroll: true });
    }
    const shell = btn.closest(".patient-tabs-shell");
    if (shell) activateTabs(shell, "resumo");
    setTimeout(updatePanels, 0);
  }
  function initOmit(root = d) {
    $$("[data-omit-field]", root).forEach((c) => {
      if (c.dataset.omitReady) return;
      c.dataset.omitReady = "1";
      const set = () => {
        const f = c.form || d,
          n = c.dataset.omitField,
          i = $(`[name="${esc(n)}"]`, f),
          box = c.closest(".optional-field");
        if (!i) return;
        i.dataset.wasRequired =
          i.dataset.wasRequired || (i.required ? "1" : "0");
        i.disabled = c.checked;
        i.required = !c.checked && i.dataset.wasRequired === "1";
        if (c.checked) i.value = "";
        box && box.classList.toggle("is-omitted", c.checked);
      };
      c.addEventListener("change", set);
      set();
    });
  }
  function initCities(root = d) {
    $$("[data-br-state]", root).forEach((st) => {
      const scope = st.closest("form") || st.closest("section") || d;
      const ct = $("[data-br-city]", scope);
      if (!ct || st.dataset.cityReady) return;
      st.dataset.cityReady = "1";
      const ib = $("[data-br-city-ibge]", scope),
        tz = $("[data-br-timezone]", scope);
      const setTz = () => {
        if (tz) tz.value = zone(st.value, ct.value || "");
      };
      const load = () => {
        const uf = st.value,
          sel = ct.dataset.selectedCity || ct.value || "";
        setTz();
        ct.disabled = true;
        if (ib) ib.value = "";
        ct.innerHTML = uf
          ? '<option value="">Carregando cidades...</option>'
          : '<option value="">Escolha primeiro o estado</option>';
        if (!uf) return;
        fetch(
          `https://servicodados.ibge.gov.br/api/v1/localidades/estados/${encodeURIComponent(uf)}/municipios?orderBy=nome`,
          { cache: "no-store" },
        )
          .then((r) => (r.ok ? r.json() : Promise.reject()))
          .then((rows) => {
            ct.innerHTML = '<option value="">Escolha a cidade</option>';
            const frag = d.createDocumentFragment();
            rows.forEach((x) => {
              const o = d.createElement("option");
              o.value = x.nome;
              o.textContent = x.nome;
              o.dataset.ibge = x.id;
              if (x.nome === sel) o.selected = true;
              frag.appendChild(o);
            });
            ct.appendChild(frag);
            ct.disabled = false;
            if (ib) ib.value = ct.selectedOptions[0]?.dataset.ibge || "";
            setTz();
          })
          .catch(() => {
            ct.innerHTML =
              '<option value="">Não foi possível carregar a lista do IBGE. Tente novamente.</option>';
            ct.disabled = true;
            if (ib) ib.value = "";
            setTz();
          });
      };
      st.addEventListener("change", () => {
        ct.dataset.selectedCity = "";
        load();
      });
      ct.addEventListener("change", () => {
        if (ib) ib.value = ct.selectedOptions[0]?.dataset.ibge || "";
        setTz();
      });
      if (st.value) load();
    });
  }
  function initClock() {
    const box = $("[data-floating-clock]"),
      out = box && $("[data-clock]", box);
    if (!out || box.dataset.clockReady) return;
    box.dataset.clockReady = "1";
    const z = box.dataset.clockTz || "America/Cuiaba",
      fmt = (t) =>
        new Intl.DateTimeFormat("pt-BR", {
          timeZone: t,
          hour: "2-digit",
          minute: "2-digit",
          hour12: false,
        }).format(new Date()),
      tick = () => {
        try {
          out.textContent = fmt(z);
        } catch (_) {
          out.textContent = fmt("America/Cuiaba");
        }
        box.setAttribute("aria-label", "Horário atual: " + out.textContent);
      };
    tick();
    setTimeout(
      () => {
        tick();
        setInterval(tick, 60000);
      },
      (60 - new Date().getSeconds()) * 1000 + 25,
    );
  }
  function initCountdown() {
    const ns = $$("[data-countdown]");
    if (!ns.length) return;
    const tick = () => {
      ns.forEach((e) => {
        let t = parseInt(e.dataset.countdown || "0", 10);
        const box = e.closest("[data-login-wait]");
        const total = Math.max(
          1,
          parseInt(
            box?.dataset.loginLockTotal ||
              e.dataset.countdownStart ||
              e.dataset.countdown ||
              "1",
            10,
          ),
        );
        if (!e.dataset.countdownStart) e.dataset.countdownStart = String(total);
        if (t > 0) {
          t--;
          e.dataset.countdown = String(t);
          e.textContent = wait(t);
        }
        const bar = box ? $("[data-countdown-bar]", box) : null;
        if (bar) {
          const pct = Math.max(0, Math.min(100, (t / total) * 100));
          bar.style.setProperty("--progress", pct + "%");
        }
        if (t <= 0) {
          e.textContent = "agora";
          const b = $("[data-login-submit]");
          if (b) {
            const f = b.closest("form[data-login-form]");
            if (f) f.dataset.loginLocked = "0";
            b.disabled = false;
            b.removeAttribute("aria-disabled");
            b.innerHTML =
              '<span class="material-symbols-rounded" aria-hidden="true">login</span><span>Entrar</span>';
            if (w.prontooSyncLoginButton) w.prontooSyncLoginButton();
          }
          if (box) {
            box.classList.add("is-ready");
            box.innerHTML =
              '<div class="login-lock-icon ready"><span class="material-symbols-rounded" aria-hidden="true">check_circle</span></div><div class="login-lock-copy"><strong>Bloqueio encerrado.</strong><span>Você já pode tentar novamente.</span></div>';
          }
        }
      });
    };
    tick();
    setInterval(tick, 1000);
  }
  function initPersonAutosuggest(root = d) {
    $$("input[data-person-autosuggest]", root).forEach((i) => {
      if (i.dataset.personSuggestReady) return;
      i.dataset.personSuggestReady = "1";
      const apply = () => {
        const id = i.getAttribute("list");
        if (!id) return;
        const list = d.getElementById(id);
        if (!list) return;
        const value = (i.value || "").trim();
        if (!value) return;
        const opt = Array.from(list.options).find(
          (o) => (o.value || "").trim().toLowerCase() === value.toLowerCase(),
        );
        if (!opt) return;
        const f = i.form || i.closest("form");
        if (!f) return;
        const set = (name, val, fmt) => {
          if (!val) return;
          const el = $(`[name="${esc(name)}"]`, f);
          if (!el) return;
          const empty =
            !String(el.value || "").trim() ||
            el.dataset.autoFilledPerson === "1";
          if (!empty) return;
          const cb = $(`[data-omit-field="${esc(name)}"]`, f);
          if (cb && cb.checked) {
            cb.checked = false;
            cb.dispatchEvent(new Event("change", { bubbles: true }));
          }
          el.value = fmt ? fmt(val) : val;
          el.dataset.autoFilledPerson = "1";
          el.dispatchEvent(new Event("input", { bubbles: true }));
          el.dispatchEvent(new Event("change", { bubbles: true }));
        };
        set("cpf", opt.dataset.cpf || "", cpf);
        set("birth_date", opt.dataset.birth || "", (v) => v);
      };
      i.addEventListener("change", apply);
      i.addEventListener("blur", apply);
      i.addEventListener(
        "input",
        () => {
          i.dataset.autoFilledPerson = "0";
          apply();
        },
        { passive: true },
      );
    });
  }
  function initPatientCpfLookup(root = d) {
    $$("input[data-patient-cpf-lookup]", root).forEach((i) => {
      if (i.dataset.patientCpfLookupReady) return;
      i.dataset.patientCpfLookupReady = "1";
      let last = "";
      let inflight = null;
      const msg = d.createElement("small");
      msg.className = "field-hint patient-cpf-lookup-hint";
      msg.setAttribute("aria-live", "polite");
      i.insertAdjacentElement("afterend", msg);
      const formOf = () => i.form || i.closest("form");
      const submitBtns = (f) => (f ? $$('button[type="submit"]', f) : []);
      const lockSave = (f, locked) => {
        submitBtns(f).forEach((b) => {
          b.disabled = !!locked;
          if (locked) b.setAttribute("aria-disabled", "true");
          else b.removeAttribute("aria-disabled");
        });
        if (f) {
          if (locked) f.dataset.patientDuplicateLocked = "1";
          else delete f.dataset.patientDuplicateLocked;
        }
      };
      const setField = (f, name, val, force = false) => {
        const el = $(`[name="${esc(name)}"]`, f);
        if (!el || val === undefined || val === null) return false;
        if (
          !force &&
          String(el.value || "").trim() !== "" &&
          el.dataset.cpfAutoFilled !== "1"
        )
          return false;
        el.value = val;
        el.dataset.cpfAutoFilled = "1";
        el.dispatchEvent(new Event("input", { bubbles: true }));
        el.dispatchEvent(new Event("change", { bubbles: true }));
        return true;
      };
      const nextEmpty = (f) => {
        const fields = $$("input,select,textarea", f).filter(
          (el) =>
            !el.disabled &&
            !el.readOnly &&
            el.type !== "hidden" &&
            el.name !== "cpf",
        );
        return fields.find((el) => !String(el.value || "").trim()) || fields[0];
      };
      const setState = (text, cls = "") => {
        msg.className =
          "field-hint patient-cpf-lookup-hint" + (cls ? " " + cls : "");
        msg.textContent = text || "";
      };
      const initialForm = formOf();
      if (initialForm && initialForm.dataset.patientExistingLock === "1")
        lockSave(initialForm, true);
      const run = () => {
        const raw = digits(i.value);
        const f = formOf();
        if (raw.length < 11) {
          last = "";
          setState("");
          if (f) {
            f.dataset.patientExistingLock = "0";
            lockSave(f, false);
          }
          return;
        }
        if (raw === last && msg.textContent) return;
        last = raw;
        if (f && f.dataset.patientExistingLock !== "1") lockSave(f, false);
        const url = i.dataset.patientCpfLookup;
        if (!url || !f) return;
        if (inflight && inflight.abort)
          try {
            inflight.abort();
          } catch (_) {}
        inflight =
          typeof AbortController !== "undefined" ? new AbortController() : null;
        setState("Verificando CPF...", "is-loading");
        const sep = url.includes("?") ? "&" : "?";
        const csrf = $('[name="csrf"]', f);
        fetch(
          `${url}${sep}cpf=${encodeURIComponent(raw)}&context=patient_create${csrf ? "&csrf=" + encodeURIComponent(csrf.value) : ""}`,
          {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
            cache: "no-store",
            signal: inflight ? inflight.signal : undefined,
          },
        )
          .then((r) => {
            const ct = (r.headers.get("content-type") || "").toLowerCase();
            if (!ct.includes("application/json"))
              return Promise.reject();
            return r.json();
          })
          .then((j) => {
            const f = formOf();
            if (!f) return;
            if (!j || j.ok === false) {
              lockSave(f, false);
              setState(j?.message || "CPF inválido.", "is-error");
              return;
            }
            if (!j.found) {
              lockSave(f, false);
              setState(
                j.message || "CPF válido. Nenhum cadastro anterior encontrado.",
                "is-new",
              );
              return;
            }
            setField(f, "name", j.name || "", true);
            setField(f, "birth_date", j.birth_date || "", true);
            if (j.already_patient && j.open_url) {
              lockSave(f, true);
              msg.className = "field-hint patient-cpf-lookup-hint is-found";
              msg.innerHTML =
                "Paciente já cadastrado. Nome e nascimento foram recuperados. ";
              const a = d.createElement("a");
              a.href = j.open_url;
              a.textContent = "Abrir ficha";
              a.className = "inline-link";
              msg.appendChild(a);
              return;
            }
            lockSave(f, false);
            setState(
              j.message || "Dados encontrados e preenchidos automaticamente.",
              "is-found",
            );
            const nx = nextEmpty(f);
            if (nx)
              try {
                nx.focus({ preventScroll: false });
              } catch (_) {
                nx.focus();
              }
          })
          .catch((err) => {
            if (err && err.name === "AbortError") return;
            const f = formOf();
            if (f && f.dataset.patientExistingLock !== "1") lockSave(f, false);
            setState(
              "Não foi possível verificar agora. Continue o cadastro manualmente; se CPF já existir, o Prontoo recuperará os dados ao salvar.",
              "is-error",
            );
          });
      };
      i.addEventListener("blur", run);
      i.addEventListener("change", run);
      i.addEventListener(
        "input",
        () => {
          const f = formOf();
          const raw = digits(i.value);
          if (raw !== last && f) {
            f.dataset.patientExistingLock = "0";
            lockSave(f, false);
          }
          if (raw.length === 11) run();
          else {
            last = "";
            setState("");
          }
        },
        { passive: true },
      );
    });
  }
  function initPersonCpfLookup(root = d) {
    $$("input[data-person-cpf-lookup]", root).forEach((i) => {
      if (i.dataset.personCpfLookupReady) return;
      i.dataset.personCpfLookupReady = "1";
      let last = "",
        seq = 0;
      const msg = d.createElement("small");
      msg.className = "field-hint person-cpf-lookup-hint";
      msg.setAttribute("aria-live", "polite");
      i.insertAdjacentElement("afterend", msg);
      const setField = (f, name, val) => {
        const el = $(`[name="${esc(name)}"]`, f);
        if (!el || !val) return;
        el.value = val;
        el.dataset.cpfAutoFilled = "1";
        el.dispatchEvent(new Event("input", { bubbles: true }));
        el.dispatchEvent(new Event("change", { bubbles: true }));
      };
      const run = () => {
        const raw = digits(i.value);
        const f = i.form || i.closest("form");
        if (raw.length < 11) {
          seq += 1;
          last = "";
          msg.textContent = "";
          return;
        }
        if (raw === last) return;
        last = raw;
        const token = ++seq;
        const url = i.dataset.personCpfLookup;
        if (!url || !f) return;
        msg.textContent = "Verificando CPF...";
        fetch(
          `${url}${url.includes("?") ? "&" : "?"}cpf=${encodeURIComponent(raw)}${i.dataset.personLookupContext ? "&context=" + encodeURIComponent(i.dataset.personLookupContext) : ""}${f && $('[name="csrf"]', f) ? "&csrf=" + encodeURIComponent($('[name="csrf"]', f).value) : ""}`,
          {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
            cache: "no-store",
          },
        )
          .then((r) => (r.ok ? r.json() : Promise.reject()))
          .then((j) => {
            if (token !== seq || digits(i.value) !== raw) return;
            if (!j || j.ok === false) {
              msg.textContent = j?.message || "CPF inválido.";
              return;
            }
            if (!j.found) {
              msg.textContent = j.message || "CPF válido. Continue o cadastro.";
              return;
            }
            setField(f, i.dataset.personNameTarget || "name", j.name || "");
            setField(
              f,
              i.dataset.personBirthTarget || "birth_date",
              j.birth_date || "",
            );
            msg.textContent =
              j.message || "Dados encontrados e preenchidos automaticamente.";
          })
          .catch(() => {
            if (token !== seq || digits(i.value) !== raw) return;
            msg.textContent =
              "Não foi possível verificar agora. Continue o cadastro manualmente.";
          });
      };
      i.addEventListener("blur", run);
      i.addEventListener("change", run);
      i.addEventListener(
        "input",
        () => {
          const raw = digits(i.value);
          if (raw !== last) {
            seq += 1;
            msg.textContent = "";
          }
          if (raw.length === 11) run();
          else if (raw.length < 11) last = "";
        },
        { passive: true },
      );
    });
  }
  function initDocumentSuggest(root, config) {
    $$(config.selector, root).forEach((i) => {
      if (i.dataset[config.readyKey]) return;
      i.dataset[config.readyKey] = "1";
      const norm = (v) =>
        String(v || "")
          .normalize("NFD")
          .replace(/[\u0300-\u036f]/g, "")
          .toLowerCase()
          .trim();
      const selectedKey = "documentSuggestSelectedValue";
      const form = () => i.form || i.closest("form");
      const hidden = () => {
        const f = form();
        return f && $(config.hiddenSelector, f);
      };
      const list = () => d.getElementById(i.getAttribute("list") || "");
      const opts = () =>
        Array.from(list()?.options || [])
          .map(config.optionFromElement)
          .filter((o) => o.value && o.id);
      const src = i.dataset[config.sourceDataset] || "";
      const cache = {};
      let timer = null,
        seq = 0;
      const panel = d.createElement("div");
      panel.className = config.panelClass;
      panel.hidden = true;
      if (i.parentElement) i.parentElement.classList.add("has-patient-suggest");
      i.insertAdjacentElement("afterend", panel);
      const clearSelection = () => {
        const h = hidden();
        if (h) h.value = "";
        delete i.dataset[selectedKey];
      };
      const choose = (o) => {
        const h = hidden();
        i.value = o.value || config.fallbackValue(o);
        if (h) h.value = o.id || "";
        i.dataset[selectedKey] = norm(i.value);
        panel.hidden = true;
        i.setAttribute("aria-expanded", "false");
        i.dispatchEvent(new Event("change", { bubbles: true }));
      };
      const apply = () => {
        const h = hidden();
        if (!h) return false;
        const v = norm(i.value);
        if (!v) {
          clearSelection();
          return false;
        }
        const all = opts();
        let matches = all.filter((o) => norm(o.value) === v);
        if (matches.length !== 1)
          matches = all.filter((o) => norm(o.name) === v);
        if (matches.length !== 1) {
          const documentDigits = digits(i.value);
          if (documentDigits)
            matches = all.filter(
              (o) => digits(config.documentValue(o)) === documentDigits,
            );
        }
        if (matches.length === 1) {
          h.value = matches[0].id;
          i.dataset[selectedKey] = v;
          return true;
        }
        if (h.value && i.dataset[selectedKey] === v) return true;
        clearSelection();
        return false;
      };
      const renderRows = (rows) => {
        panel.innerHTML = "";
        rows.slice(0, 10).forEach((o) => {
          const b = d.createElement("button");
          b.type = "button";
          b.className = config.optionClass;
          b.innerHTML = "<strong></strong><small></small>";
          $("strong", b).textContent =
            o.name || String(o.value || "").split(" · ")[0] || config.fallbackName;
          $("small", b).textContent = config.smallText(o);
          b.addEventListener("mousedown", (e) => e.preventDefault());
          b.addEventListener("click", () => choose(o));
          panel.appendChild(b);
        });
        panel.hidden = rows.length === 0;
        i.setAttribute("aria-expanded", rows.length ? "true" : "false");
      };
      const localRows = (query) =>
        opts()
          .filter((o) => config.localMatch(o, query, norm))
          .map(config.normalizeLocal)
          .slice(0, 10);
      const run = (token) => {
        if (token !== seq) return;
        const query = norm(i.value);
        apply();
        if (query.length < 1) {
          panel.hidden = true;
          i.setAttribute("aria-expanded", "false");
          return;
        }
        const local = localRows(query);
        renderRows(local);
        if (!src) return;
        if (cache[query]) {
          if (token === seq) renderRows(cache[query]);
          return;
        }
        fetch(
          `${src}${src.includes("?") ? "&" : "?"}q=${encodeURIComponent(i.value.trim())}&limit=12`,
          {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
            cache: "no-store",
          },
        )
          .then((r) => (r.ok ? r.json() : Promise.reject()))
          .then((j) => {
            if (token !== seq || norm(i.value) !== query) return;
            const rows = j && j.ok && Array.isArray(j.items) ? j.items : [];
            cache[query] = rows;
            renderRows(rows.length ? rows : local);
          })
          .catch(() => {
            if (token === seq && norm(i.value) === query) renderRows(local);
          });
      };
      const schedule = () => {
        clearTimeout(timer);
        const token = ++seq;
        timer = setTimeout(() => run(token), 120);
      };
      i.setAttribute("role", "combobox");
      i.setAttribute("aria-expanded", "false");
      i.addEventListener(
        "input",
        () => {
          if (i.dataset[selectedKey] !== norm(i.value)) clearSelection();
          schedule();
        },
        { passive: true },
      );
      i.addEventListener("focus", schedule);
      i.addEventListener("change", apply);
      i.addEventListener("blur", () =>
        setTimeout(() => {
          apply();
          panel.hidden = true;
          i.setAttribute("aria-expanded", "false");
        }, 140),
      );
      i.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
          seq += 1;
          panel.hidden = true;
          i.setAttribute("aria-expanded", "false");
        }
        if (e.key === "Enter" && !panel.hidden) {
          const first = $(config.firstOptionSelector, panel);
          if (first) {
            e.preventDefault();
            first.click();
          }
        }
      });
      const f = form();
      if (f && !f.dataset[config.submitReadyKey]) {
        f.dataset[config.submitReadyKey] = "1";
        f.addEventListener("submit", () => {
          $$(config.selector, f).forEach((input) => {
            try {
              input.dispatchEvent(new Event("change", { bubbles: true }));
            } catch (_) {}
          });
        });
      }
      const initialHidden = hidden();
      if (initialHidden?.value && norm(i.value)) {
        i.dataset[selectedKey] = norm(i.value);
      }
      apply();
    });
  }
  function initPatientDocumentSuggest(root = d) {
    initDocumentSuggest(root, {
      selector: "input[data-patient-document-suggest]",
      readyKey: "patientDocumentSuggestReady",
      hiddenSelector: "[data-patient-id-target]",
      sourceDataset: "patientSuggestUrl",
      panelClass: "patient-suggest-panel",
      optionClass: "patient-suggest-option",
      firstOptionSelector: ".patient-suggest-option",
      submitReadyKey: "patientDocumentSubmitReady",
      fallbackName: "Paciente",
      optionFromElement: (o) => ({
        value: o.value || "",
        id: o.dataset.patientId || "",
        name: o.dataset.patientName || "",
        birth: o.dataset.birth || "",
        cpf: o.dataset.cpf || "",
      }),
      fallbackValue: (o) => [o.name, o.birth].filter(Boolean).join(" · "),
      documentValue: (o) => o.cpf,
      smallText: (o) =>
        o.birth ? "Nascimento: " + o.birth : "Nascimento não informado",
      localMatch: (o, query, norm) =>
        norm(o.name).startsWith(query) ||
        norm(o.value).startsWith(query) ||
        (digits(query) && digits(o.cpf).includes(digits(query))),
      normalizeLocal: (o) => ({
        id: o.id,
        name: o.name,
        value: o.value,
        birth: o.birth
          ? o.birth.split("-").reverse().join("/")
          : String(o.value || "").split(" · ").slice(1).join(" · "),
        cpf: o.cpf,
      }),
    });
  }
  function initCounterpartyDocumentLookup(root = d) {
    $$("input[data-counterparty-document-lookup]", root).forEach((i) => {
      if (i.dataset.counterpartyLookupReady) return;
      i.dataset.counterpartyLookupReady = "1";
      let last = "",
        seq = 0;
      const msg = d.createElement("small");
      msg.className = "field-hint counterparty-document-lookup-hint";
      msg.setAttribute("aria-live", "polite");
      i.insertAdjacentElement("afterend", msg);
      const setField = (f, name, val) => {
        const el = $(`[name="${esc(name)}"]`, f);
        if (!el || val === undefined || val === null) return false;
        if (String(val || "") === "" && String(el.value || "") !== "")
          return false;
        el.value = val;
        el.dataset.documentAutoFilled = "1";
        el.dispatchEvent(new Event("input", { bubbles: true }));
        el.dispatchEvent(new Event("change", { bubbles: true }));
        return true;
      };
      const nextEmpty = (f) => {
        const fields = $$("input,select,textarea", f).filter(
          (el) =>
            !el.disabled &&
            !el.readOnly &&
            el.type !== "hidden" &&
            el.name !== "doc",
        );
        return fields.find((el) => !String(el.value || "").trim()) || fields[0];
      };
      const run = () => {
        const raw = digits(i.value);
        const f = i.form || i.closest("form");
        if (raw.length !== 11 && raw.length !== 14) {
          seq += 1;
          last = "";
          msg.textContent = "";
          return;
        }
        if (raw === last) return;
        last = raw;
        const token = ++seq;
        const url = i.dataset.counterpartyDocumentLookup;
        if (!url || !f) return;
        msg.textContent = "Verificando CPF/CNPJ...";
        fetch(
          `${url}${url.includes("?") ? "&" : "?"}doc=${encodeURIComponent(raw)}`,
          {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
            cache: "no-store",
          },
        )
          .then((r) => (r.ok ? r.json() : Promise.reject()))
          .then((j) => {
            if (token !== seq || digits(i.value) !== raw) return;
            if (!j || j.ok === false) {
              msg.textContent = j?.message || "Documento inválido.";
              return;
            }
            if (!j.found) {
              msg.textContent =
                j.message || "Documento válido. Continue o cadastro do credor.";
              return;
            }
            setField(f, "name", j.name || "");
            setField(f, "birth_date", j.birth_date || "");
            msg.textContent =
              j.message || "Dados encontrados e preenchidos automaticamente.";
            const nx = nextEmpty(f);
            if (nx)
              try {
                nx.focus({ preventScroll: false });
              } catch (_) {
                nx.focus();
              }
          })
          .catch(() => {
            if (token !== seq || digits(i.value) !== raw) return;
            msg.textContent =
              "Não foi possível verificar agora. Continue o cadastro manualmente.";
          });
      };
      i.addEventListener("blur", run);
      i.addEventListener("change", run);
      i.addEventListener(
        "input",
        () => {
          const raw = digits(i.value);
          if (raw !== last) {
            seq += 1;
            msg.textContent = "";
          }
          if (raw.length === 11 || raw.length === 14) run();
          else last = "";
        },
        { passive: true },
      );
    });
  }
  function initCounterpartyDocumentSuggest(root = d) {
    initDocumentSuggest(root, {
      selector: "input[data-counterparty-document-suggest]",
      readyKey: "counterpartyDocumentSuggestReady",
      hiddenSelector: "[data-counterparty-id-target]",
      sourceDataset: "counterpartySuggestUrl",
      panelClass: "patient-suggest-panel counterparty-suggest-panel",
      optionClass: "patient-suggest-option counterparty-suggest-option",
      firstOptionSelector: ".counterparty-suggest-option",
      submitReadyKey: "counterpartyDocumentSubmitReady",
      fallbackName: "Credor",
      optionFromElement: (o) => ({
        value: o.value || "",
        id: o.dataset.counterpartyId || "",
        name: o.dataset.counterpartyName || "",
        document: o.dataset.counterpartyDoc || "",
        documentRaw: o.dataset.counterpartyDocRaw || "",
      }),
      fallbackValue: (o) => [o.name, o.document].filter(Boolean).join(" · "),
      documentValue: (o) => o.documentRaw || o.document,
      smallText: (o) =>
        o.document ? "CPF/CNPJ: " + o.document : "Documento não informado",
      localMatch: (o, query, norm) =>
        norm(o.name).startsWith(query) ||
        norm(o.value).startsWith(query) ||
        (digits(query) &&
          digits(o.documentRaw || o.document).includes(digits(query))),
      normalizeLocal: (o) => ({
        id: o.id,
        name: o.name,
        value: o.value,
        document: o.document,
        documentRaw: o.documentRaw,
      }),
    });
  }
  function initPatientLiveSearch(root = d) {
    $$("form[data-patient-live-search]", root).forEach((f) => {
      if (f.dataset.patientLiveReady) return;
      f.dataset.patientLiveReady = "1";
      const key = f.dataset.patientLiveSearch || "patients",
        i = $("[data-patient-live-input]", f),
        box = $(`[data-patient-live-results="${esc(key)}"]`, d);
      if (!i || !box) return;
      const src = i.dataset.patientSuggestUrl || "";
      const chips =
        $("[data-patient-filter-nav]", d) || $(".patient-filter-chips", d);
      const baseTpl = $("[data-patient-filter-base]", d);
      const baseChips = baseTpl
        ? baseTpl.innerHTML
        : chips
          ? chips.innerHTML
          : "";
      let initial = box.innerHTML,
        timer = null,
        seq = 0;
      const norm = (v) =>
        String(v || "")
          .normalize("NFD")
          .replace(/[\u0300-\u036f]/g, "")
          .toLowerCase()
          .trim();
      const set = (el, sel, val) => {
        const n = $(sel, el);
        if (n) n.textContent = val || "";
      };
      const foundChip = (count) =>
        '<span class="patient-filter-chip active is-active patient-filter-found" aria-current="page"><span class="material-symbols-rounded" aria-hidden="true">manage_search</span><span>Encontrados</span><small>' +
        String(Math.max(0, Number(count) || 0)) +
        "</small></span>";
      const syncFound = (q, count) => {
        if (!chips) return;
        if (!q) {
          chips.innerHTML = baseChips;
          return;
        }
        chips.innerHTML = foundChip(count) + baseChips;
      };
      const row = (o) => {
        const a = d.createElement("article");
        const level = ["ok", "warn", "bad"].includes(o.status_level)
          ? o.status_level
          : "ok";
        a.className =
          "patient-card-row ds-person-row ds-patient-row patient-status-" +
          level;
        a.dataset.patientRow = "1";
        a.dataset.patientSearch = [
          o.name,
          o.birth,
          o.age,
          o.cpf,
          o.phone,
          o.status_label,
          o.last_consultation_date,
        ]
          .filter(Boolean)
          .join(" ");
        a.innerHTML =
          '<span class="patient-card-avatar ds-person-avatar" aria-hidden="true"><span class="material-symbols-rounded" aria-hidden="true">personal_injury</span></span><div class="patient-card-main ds-person-main"><div class="patient-card-title ds-person-title"><strong></strong><span class="pill patient-status-pill ds-status-pill"></span></div><div class="patient-card-meta ds-person-meta"><span><span class="material-symbols-rounded" aria-hidden="true">cake</span><span data-birth></span></span><span><span class="material-symbols-rounded" aria-hidden="true">badge</span><span data-cpf></span></span><span><span class="material-symbols-rounded" aria-hidden="true">call</span><span data-phone></span></span><span data-last-consultation-wrap hidden><span class="material-symbols-rounded" aria-hidden="true">stethoscope</span><span data-last-consultation></span></span></div></div><div class="patient-card-actions ds-person-actions"><a class="primary small"><span class="material-symbols-rounded" aria-hidden="true">folder_open</span><span>Abrir ficha</span></a></div>';
        const title = $(".patient-card-title", a),
          titleStrong = $(".patient-card-title strong", a);
        if (o.today_appointment_time && title && titleStrong) {
          const sp = d.createElement("span");
          sp.className = "patient-schedule-pill";
          sp.title = "Horário do agendamento de hoje";
          sp.innerHTML =
            '<span class="material-symbols-rounded" aria-hidden="true">schedule</span><span></span>';
          const spText = $("span:last-child", sp);
          if (spText) spText.textContent = o.today_appointment_time;
          title.insertBefore(sp, titleStrong);
        }
        set(a, ".patient-card-title strong", o.name || "Paciente");
        const pill = $(".patient-card-title .pill", a);
        if (pill) {
          pill.className = "pill patient-status-pill ds-status-pill " + level;
          pill.textContent = o.status_label || "Ficha completa";
          if (o.status_message) pill.title = o.status_message;
        }
        set(
          a,
          "[data-birth]",
          [
            o.birth || "Nascimento não informado",
            o.age || "Idade não informada",
          ]
            .filter(Boolean)
            .join(" · "),
        );
        set(a, "[data-cpf]", o.cpf || "CPF não informado");
        set(a, "[data-phone]", o.phone || "Sem telefone");
        const lastWrap = $("[data-last-consultation-wrap]", a);
        if (lastWrap && o.last_consultation_date) {
          lastWrap.hidden = false;
          set(
            a,
            "[data-last-consultation]",
            "Última consulta: " + o.last_consultation_date,
          );
        }
        const link = $("a", a);
        if (link) link.href = o.open_url || "#";
        return a;
      };
      const renderRemote = (items) => {
        syncFound(norm(i.value), items.length);
        box.innerHTML = "";
        if (!items.length) {
          box.innerHTML =
            '<div class="empty patient-directory-empty"><span class="material-symbols-rounded" aria-hidden="true">manage_search</span><strong>Nenhum paciente encontrado para esta busca.</strong><span>Revise o termo digitado ou limpe os filtros.</span></div>';
          return;
        }
        const wrap = d.createElement("div");
        wrap.className =
          "patient-directory-list ds-person-list ds-patient-list";
        items.forEach((o) => wrap.appendChild(row(o)));
        box.appendChild(wrap);
      };
      const filterLocal = (q) => {
        const rows = $$("[data-patient-row]", box);
        let shown = 0;
        rows.forEach((r) => {
          const ok = norm(r.dataset.patientSearch || r.textContent).includes(q);
          r.hidden = !ok;
          if (ok) shown++;
        });
        syncFound(q, shown);
        let empty = $("[data-patient-live-empty]", box);
        if (!empty) {
          empty = d.createElement("div");
          empty.className = "empty patient-directory-empty";
          empty.dataset.patientLiveEmpty = "1";
          empty.innerHTML =
            '<span class="material-symbols-rounded" aria-hidden="true">manage_search</span><strong>Nenhum paciente encontrado para esta busca.</strong><span>Revise o termo digitado ou limpe os filtros.</span>';
          empty.hidden = true;
          box.appendChild(empty);
        }
        empty.hidden = shown !== 0;
      };
      const run = () => {
        const q = norm(i.value);
        if (q === "") {
          syncFound("", 0);
          box.innerHTML = initial;
          return;
        }
        if (src) {
          const thisSeq = ++seq;
          fetch(
            `${src}${src.includes("?") ? "&" : "?"}q=${encodeURIComponent(i.value.trim())}&limit=80`,
            {
              headers: { Accept: "application/json" },
              credentials: "same-origin",
              cache: "no-store",
            },
          )
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((j) => {
              if (thisSeq !== seq) return;
              renderRemote(j && j.ok && Array.isArray(j.items) ? j.items : []);
            })
            .catch(() => filterLocal(q));
          return;
        }
        filterLocal(q);
      };
      i.addEventListener(
        "input",
        () => {
          clearTimeout(timer);
          timer = setTimeout(run, 120);
        },
        { passive: true },
      );
      i.addEventListener("search", run);
      f.addEventListener("submit", (e) => {
        if (i.value.trim()) {
          e.preventDefault();
          run();
        }
      });
      if (i.value.trim()) run();
    });
  }
  function initProcedureSelects(root = d) {
    $$("select[data-procedure-select]", root).forEach((sel) => {
      if (sel.dataset.procedureReady) return;
      sel.dataset.procedureReady = "1";
      const form = sel.form || sel.closest("form");
      const hint =
        $("[data-procedure-summary]", sel.closest(".field") || form) ||
        sel.parentElement?.querySelector("[data-procedure-summary]");
      const start = form && $('input[name="start_at"]', form),
        end = form && $('input[name="end_at"]', form);
      const pad = (n) => String(n).padStart(2, "0");
      const parseLocal = (v) => {
        if (!v) return null;
        const dt = new Date(v);
        return Number.isNaN(dt.getTime()) ? null : dt;
      };
      const formatLocal = (dt) =>
        `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
      const addMin = (v, min) => {
        const dt = parseLocal(v);
        if (!dt || !min) return "";
        dt.setMinutes(dt.getMinutes() + parseInt(min, 10));
        return formatLocal(dt);
      };
      const currentDuration = () =>
        parseInt(sel.selectedOptions[0]?.dataset.duration || "0", 10) || 0;
      const minEnd = () => addMin(start && start.value, currentDuration());
      const belowMin = () => {
        const min = parseLocal(minEnd()),
          cur = parseLocal(end && end.value);
        return !!(min && cur && cur.getTime() < min.getTime());
      };
      const setEndToMin = () => {
        const v = minEnd();
        if (v && end) {
          end.value = v;
          end.min = v;
          form && (form.dataset.procedureAutoEnd = "1");
          return true;
        }
        return false;
      };
      const syncPayment = () => {
        if (!form) return;
        const opt = sel.selectedOptions[0];
        const price = opt?.dataset.price || "R$ 0,00";
        const priceCents = parseInt(opt?.dataset.priceCents || "0", 10) || 0;
        $$("[data-appointment-payment-amount-display]", form).forEach((i) => {
          i.value = price;
        });
        $$("[data-appointment-payment-amount-hidden]", form).forEach((i) => {
          i.value = price;
        });
        $$("[data-appointment-payment]", form).forEach((box) => {
          box.dataset.procedurePriceCents = String(priceCents);
        });
      };
      const sync = (reason) => {
        const opt = sel.selectedOptions[0];
        const dur = currentDuration();
        if (hint) hint.textContent = opt?.dataset.summary || "";
        syncPayment();
        if (!start || !end || dur <= 0 || !start.value) {
          if (end) end.removeAttribute("min");
          return;
        }
        const min = minEnd();
        if (min) end.min = min;
        if (
          reason === "procedure" ||
          reason === "start" ||
          !end.value ||
          form?.dataset.procedureAutoEnd === "1" ||
          belowMin()
        )
          setEndToMin();
      };
      sel.addEventListener("change", () => sync("procedure"));
      start && start.addEventListener("change", () => sync("start"));
      start &&
        start.addEventListener("input", () => sync("start"), { passive: true });
      end &&
        end.addEventListener(
          "input",
          () => {
            if (form) form.dataset.procedureAutoEnd = "0";
            if (belowMin())
              end.setCustomValidity(
                "O fim não pode ser menor que a duração cadastrada para o procedimento selecionado.",
              );
            else end.setCustomValidity("");
          },
          { passive: true },
        );
      end &&
        end.addEventListener("change", () => {
          if (belowMin()) setEndToMin();
          else if (form) form.dataset.procedureAutoEnd = "0";
          end.setCustomValidity("");
        });
      form &&
        form.addEventListener("submit", (e) => {
          sync("submit");
          if (end && belowMin()) {
            e.preventDefault();
            setEndToMin();
            try {
              end.reportValidity();
            } catch (_) {
              end.focus();
            }
          }
        });
      sync("init");
    });
  }
  function initAppointmentPayments(root = d) {
    $$("[data-appointment-payment]", root).forEach((box) => {
      if (box.dataset.appointmentPaymentReady) return;
      box.dataset.appointmentPaymentReady = "1";
      const form = box.closest("form") || box;
      const paid = $("[data-appointment-paid-toggle]", box);
      const details = $("[data-appointment-payment-details]", box);
      const method = $("[data-appointment-payment-method]", box);
      const dest = $("[data-appointment-payment-destination]", box);
      const sync = () => {
        const checked = !!(paid && paid.checked);
        if (details) details.hidden = !checked;
        if (method) {
          method.required = checked;
          if (!checked) method.value = "";
        }
        const needsDest =
          checked && method && method.value && method.value !== "dinheiro";
        if (dest) {
          dest.hidden = !needsDest;
          $$("select,input", dest).forEach((el) => {
            el.required = needsDest;
            if (!needsDest && el.tagName === "SELECT") el.value = "";
          });
        }
      };
      paid && paid.addEventListener("change", sync);
      method && method.addEventListener("change", sync);
      form && form.addEventListener("submit", () => sync());
      sync();
    });
  }
  function initTaskTargetFields(root = d) {
    $$("form[data-task-create-form]", root).forEach((form) => {
      if (form.dataset.taskTargetReady) return;
      form.dataset.taskTargetReady = "1";
      const scope =
        $("[data-task-target-scope]", form) || $('[name="target_scope"]', form);
      const fields = $$("[data-task-target-field]", form);
      const sync = () => {
        const v = scope ? scope.value : "";
        fields.forEach((box) => {
          const key = box.dataset.taskTargetField || "";
          const show = key === v;
          box.hidden = !show;
          $$("input,select,textarea", box).forEach((el) => {
            el.disabled = !show;
            if (el.name === "target_role" || el.name === "target_user_id")
              el.required = show;
            if (!show) el.value = "";
          });
        });
      };
      scope && scope.addEventListener("change", sync);
      sync();
    });
  }
  function scorePassword(v) {
    v = String(v || "");
    let score = 0;
    const types = [/[a-z]/, /[A-Z]/, /\d/, /[^a-zA-Z\d]/].reduce(
      (n, re) => n + (re.test(v) ? 1 : 0),
      0,
    );
    if (v.length >= 8) score++;
    if (v.length >= 10) score++;
    if (v.length >= 14) score++;
    score += types;
    return Math.max(0, Math.min(6, score));
  }
  function passwordLabel(score, len) {
    if (!len) return ["", "Escolha uma senha forte"];
    if (score < 3) return ["weak", "Senha fraca"];
    if (score < 5) return ["medium", "Senha média"];
    return ["strong", "Senha forte"];
  }
  function initPasswordStrength(root = d) {
    $$('input[type="password"][data-password-strength]', root).forEach((i) => {
      if (i.dataset.passwordStrengthReady) return;
      i.dataset.passwordStrengthReady = "1";
      const box = d.createElement("div");
      box.className = "password-strength";
      box.setAttribute("aria-live", "polite");
      box.innerHTML = "<span><i></i></span><em>Escolha uma senha forte</em>";
      i.insertAdjacentElement("afterend", box);
      const bar = $("span", box),
        txt = $("em", box);
      const update = () => {
        const s = scorePassword(i.value),
          r = passwordLabel(s, i.value.length),
          pct = Math.round((s / 6) * 100);
        box.classList.remove("weak", "medium", "strong");
        if (r[0]) box.classList.add(r[0]);
        bar.style.setProperty("--strength", pct + "%");
        txt.textContent = r[1];
      };
      i.addEventListener("input", update, { passive: true });
      i.addEventListener("change", update);
      update();
    });
  }
  function syncDocumentEditor(wrap) {
    const ed = $("[data-doc-editor]", wrap),
      inp = $("[data-doc-editor-input]", wrap);
    if (ed && inp) {
      inp.value = ed.innerHTML.trim();
      const wc = $("[data-doc-word-count]", wrap);
      if (wc) {
        const text = (ed.textContent || "").trim().replace(/\s+/g, " ");
        const words = text ? text.split(" ").filter(Boolean).length : 0;
        wc.textContent = words === 1 ? "1 palavra" : words + " palavras";
      }
    }
  }
  function insertHtmlAtCaret(html) {
    let sel = w.getSelection && w.getSelection();
    if (!sel || !sel.rangeCount) {
      d.execCommand("insertHTML", false, html);
      return;
    }
    const range = sel.getRangeAt(0);
    range.deleteContents();
    const temp = d.createElement("div");
    temp.innerHTML = html;
    const frag = d.createDocumentFragment();
    let node, last;
    while ((node = temp.firstChild)) {
      last = frag.appendChild(node);
    }
    range.insertNode(frag);
    if (last) {
      range.setStartAfter(last);
      range.collapse(true);
      sel.removeAllRanges();
      sel.addRange(range);
    }
  }
  function insertPlainAtCaret(text) {
    insertHtmlAtCaret(
      String(text || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\n/g, "<br>"),
    );
  }
  function updateDocumentToolbar(wrap) {
    const ed = $("[data-doc-editor]", wrap);
    if (!ed) return;
    $$("[data-doc-cmd]", wrap).forEach((b) => {
      const cmd = b.dataset.docCmd || "";
      if (
        !/^(bold|italic|underline|insertUnorderedList|insertOrderedList|justifyLeft|justifyCenter|justifyRight|justifyFull)$/i.test(
          cmd,
        )
      )
        return;
      try {
        b.classList.toggle("is-active", !!d.queryCommandState(cmd));
      } catch (_) {
        b.classList.remove("is-active");
      }
    });
  }
  function initDocumentEditors(root = d) {
    $$("[data-doc-editor-wrap]", root).forEach((wrap) => {
      if (wrap.dataset.docEditorReady) return;
      wrap.dataset.docEditorReady = "1";
      const ed = $("[data-doc-editor]", wrap),
        inp = $("[data-doc-editor-input]", wrap);
      if (!ed || !inp) return;
      const sync = () => {
        syncDocumentEditor(wrap);
        updateDocumentToolbar(wrap);
      };
      ed.addEventListener("input", sync, { passive: true });
      ed.addEventListener("blur", sync, { passive: true });
      ed.addEventListener("keyup", sync, { passive: true });
      ed.addEventListener("mouseup", () => setTimeout(sync, 0), {
        passive: true,
      });
      ed.addEventListener("paste", (e) => {
        e.preventDefault();
        const cb = e.clipboardData || w.clipboardData;
        const text = cb ? cb.getData("text/plain") : "";
        insertPlainAtCaret(text);
        sync();
      });
      $$("[data-doc-cmd]", wrap).forEach((b) =>
        b.addEventListener("click", (e) => {
          e.preventDefault();
          ed.focus();
          const cmd = b.dataset.docCmd || "",
            val = b.dataset.docValue || null;
          try {
            d.execCommand(cmd, false, val);
          } catch (_) {}
          sync();
        }),
      );
      $$("[data-doc-field]", wrap).forEach((b) =>
        b.addEventListener("click", (e) => {
          e.preventDefault();
          ed.focus();
          insertPlainAtCaret(b.dataset.docField || "");
          sync();
        }),
      );
      sync();
    });
  }
  function initClinicVisualPicker(root = d) {
    const form = $(".clinic-settings-form", root);
    if (!form || form.dataset.clinicVisualReady) return;
    if (
      !form.querySelector(
        'input[name="clinic_icon"],input[name="accent_color"]',
      )
    )
      return;
    form.dataset.clinicVisualReady = "1";
    const preview = $(".clinic-visual-preview .brand-mark", form),
      body = d.body;
    const clamp = (v) => Math.max(0, Math.min(255, Math.round(v)));
    const hexRgb = (hex) => {
      hex = String(hex || "")
        .replace("#", "")
        .trim();
      if (hex.length === 3)
        hex = hex
          .split("")
          .map((x) => x + x)
          .join("");
      if (!/^[0-9a-f]{6}$/i.test(hex)) hex = "0f766e";
      return [
        parseInt(hex.slice(0, 2), 16),
        parseInt(hex.slice(2, 4), 16),
        parseInt(hex.slice(4, 6), 16),
      ];
    };
    const rgbHex = (rgb) =>
      "#" + rgb.map((v) => clamp(v).toString(16).padStart(2, "0")).join("");
    const mix = (a, b, w) => {
      const A = hexRgb(a),
        B = hexRgb(b),
        x = Math.max(0, Math.min(1, w));
      return rgbHex([
        A[0] * x + B[0] * (1 - x),
        A[1] * x + B[1] * (1 - x),
        A[2] * x + B[2] * (1 - x),
      ]);
    };
    const lum = (hex) => {
      const c = hexRgb(hex).map((v) => {
        v /= 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
      });
      return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    };
    const contrast = (a, b) => {
      const A = lum(a),
        B = lum(b),
        hi = Math.max(A, B),
        lo = Math.min(A, B);
      return (hi + 0.05) / (lo + 0.05);
    };
    const on = (hex) =>
      contrast(hex, "#ffffff") >= contrast(hex, "#17181c")
        ? "#ffffff"
        : "#17181c";
    const set = (n, v) => body && body.style.setProperty(n, v);
    const update = () => {
      const icon =
          $('input[name="clinic_icon"]:checked', form)?.value ||
          "medical_services",
        color = (
          $('input[name="accent_color"]:checked', form)?.value || "#334155"
        ).toLowerCase();
      const rgb = hexRgb(color),
        strong =
          contrast(mix(color, "#000000", 0.82), "#ffffff") < 4.5
            ? mix(color, "#000000", 0.7)
            : mix(color, "#000000", 0.82),
        hover = mix(color, "#000000", 0.88),
        pressed = mix(color, "#000000", 0.76),
        soft = mix(color, "#ffffff", 0.105),
        subtle = mix(color, "#ffffff", 0.05),
        muted = mix(color, "#ffffff", 0.22),
        border = mix(color, "#ffffff", 0.34),
        surface = mix(color, "#ffffff", 0.018),
        surfaceElevated = mix(color, "#ffffff", 0.01),
        surfaceSoft = mix(color, "#ffffff", 0.055),
        bg = mix(color, "#ffffff", 0.045),
        line = mix(color, "#ffffff", 0.155),
        lineStrong = mix(color, "#ffffff", 0.245),
        controlBorder = mix(color, "#ffffff", 0.205),
        surfaceTint = mix(color, "#ffffff", 0.035),
        focus = `rgba(${rgb[0]},${rgb[1]},${rgb[2]},.16)`,
        shadow = `rgba(${rgb[0]},${rgb[1]},${rgb[2]},.22)`,
        text = on(color);
      if (preview)
        preview.innerHTML =
          '<span class="material-symbols-rounded" aria-hidden="true">' +
          icon.replace(/[^a-z0-9_]/gi, "") +
          "</span>";
      set("--clinic-accent", color);
      set("--clinic-accent-rgb", rgb.join(","));
      set("--clinic-accent-strong", strong);
      set("--clinic-accent-hover", hover);
      set("--clinic-accent-pressed", pressed);
      set("--clinic-accent-soft", soft);
      set("--clinic-accent-subtle", subtle);
      set("--clinic-accent-muted", muted);
      set("--clinic-accent-border", border);
      set("--clinic-accent-focus", focus);
      set("--clinic-accent-shadow", shadow);
      set("--clinic-on-accent", text);
      set("--clinic-surface-tint", surfaceTint);
      set("--clinic-clock-bg", color);
      set("--clinic-clock-border", strong);
      set("--clinic-clock-ink", "#ffffff");
      set("--bg", bg);
      set("--surface", surface);
      set("--surface-elevated", surfaceElevated);
      set("--surface-raised", surfaceSoft);
      set("--surface-soft", surfaceSoft);
      set("--line", line);
      set("--line-strong", lineStrong);
      set("--control-border", controlBorder);
      set("--brand", color);
      set("--brand-rgb", rgb.join(","));
      set("--brand-dark", strong);
      set("--brand-soft", soft);
      set("--brand-soft-2", subtle);
      set("--brand-border", border);
      set("--brand-focus", focus);
      set("--brand-shadow", shadow);
      set("--on-brand", text);
      set("--md-sys-color-primary", color);
      set("--md-sys-color-on-primary", text);
      set("--md-sys-color-primary-container", soft);
      set("--md-sys-color-on-primary-container", strong);
      set("--md-sys-color-secondary", strong);
      set("--md-sys-color-on-secondary", "#ffffff");
      set("--md-sys-color-secondary-container", subtle);
      set("--md-sys-color-on-secondary-container", strong);
      set("--md-sys-color-tertiary", hover);
      set("--md-sys-color-on-tertiary", "#ffffff");
      set("--md-sys-color-tertiary-container", muted);
      set("--md-sys-color-on-tertiary-container", strong);
      set("--md-sys-color-surface", surface);
      set("--md-sys-color-surface-dim", bg);
      set("--md-sys-color-surface-bright", surfaceElevated);
      set("--md-sys-color-surface-container-lowest", "#ffffff");
      set("--md-sys-color-surface-container-low", surfaceElevated);
      set("--md-sys-color-surface-container", surface);
      set("--md-sys-color-surface-container-high", surfaceSoft);
      set("--md-sys-color-surface-container-highest", subtle);
      set("--md-sys-color-surface-variant", surfaceSoft);
      set("--md-sys-color-on-surface", "#17181c");
      set("--md-sys-color-on-surface-variant", "#6b7280");
      set("--md-sys-color-outline", lineStrong);
      set("--md-sys-color-outline-variant", line);
      set("--md-sys-color-inverse-surface", "#111827");
      set("--md-sys-color-inverse-on-surface", "#f8fafc");
      set("--md-sys-color-inverse-primary", muted);
      set("--md-sys-color-shadow", "rgba(15,23,42,.18)");
      set("--md-sys-color-scrim", "rgba(15,23,42,.36)");
      set("--md-sys-state-hover", `rgba(${rgb[0]},${rgb[1]},${rgb[2]},.08)`);
      set("--md-sys-state-focus", `rgba(${rgb[0]},${rgb[1]},${rgb[2]},.12)`);
      set("--md-sys-state-pressed", `rgba(${rgb[0]},${rgb[1]},${rgb[2]},.12)`);
    };
    form.addEventListener("change", (e) => {
      if (
        e.target.matches('input[name="clinic_icon"],input[name="accent_color"]')
      )
        update();
    });
    update();
  }
  function initLoginScrollLock() {
    if (
      !(
        d.body &&
        d.body.classList.contains("public") &&
        d.body.dataset.route === "login"
      )
    )
      return;
    const sync = () => {
      d.documentElement.classList.remove("login-no-scroll");
      d.body.classList.remove("login-no-scroll");
      const overflow = Math.ceil(
        Math.max(d.documentElement.scrollHeight, d.body.scrollHeight) -
          w.innerHeight,
      );
      if (overflow <= 1) {
        try {
          w.scrollTo(0, 0);
        } catch (_) {}
        d.documentElement.classList.add("login-no-scroll");
        d.body.classList.add("login-no-scroll");
      }
    };
    clearTimeout(w.__prontooLoginScrollTimer);
    w.__prontooLoginScrollTimer = setTimeout(sync, 60);
    if (!w.__prontooLoginScrollReady) {
      w.__prontooLoginScrollReady = true;
      w.addEventListener("resize", () => {
        clearTimeout(w.__prontooLoginScrollTimer);
        w.__prontooLoginScrollTimer = setTimeout(sync, 120);
      });
      w.addEventListener("orientationchange", () => {
        clearTimeout(w.__prontooLoginScrollTimer);
        w.__prontooLoginScrollTimer = setTimeout(sync, 260);
      });
      w.addEventListener("load", () => {
        clearTimeout(w.__prontooLoginScrollTimer);
        w.__prontooLoginScrollTimer = setTimeout(sync, 120);
      });
    }
  }
  function initVersionGate(root = d) {
    const route = d.body?.dataset.route || "";
    if (route !== "login" || w.__prontooVersionGateReady) return;
    w.__prontooVersionGateReady = true;
    const current =
      d.body?.dataset.appVersion ||
      $('meta[name="prontoo-version"]')?.content ||
      "";
    const forms = $$("form[data-login-form]", root);
    const submitButtons = forms.flatMap((f) =>
      $$('button,input[type="submit"]', f),
    );
    let gate = null;
    const ensureGate = () => {
      if (gate) return gate;
      gate = d.createElement("aside");
      gate.className = "version-update-pill";
      gate.setAttribute("role", "status");
      gate.setAttribute("aria-live", "polite");
      gate.hidden = true;
      gate.innerHTML =
        '<span class="material-symbols-rounded" aria-hidden="true">sync</span><span>Uma atualização está sendo recebida</span>';
      d.body.appendChild(gate);
      return gate;
    };
    const setLoginEnabled = (enabled) => {
      forms.forEach((f) => f.classList.toggle("is-updating", !enabled));
      submitButtons.forEach((b) => {
        if (!b.dataset.wasDisabled)
          b.dataset.wasDisabled = b.disabled ? "1" : "0";
        if (enabled) {
          const f = b.closest("form[data-login-form]");
          if (
            f &&
            f.hasAttribute("data-login-autotest") &&
            f.dataset.autotestReady !== "1"
          ) {
            b.disabled = true;
            b.setAttribute("aria-disabled", "true");
          } else {
            if (b.dataset.wasDisabled !== "1") b.disabled = false;
            b.removeAttribute("aria-disabled");
          }
        } else {
          b.disabled = true;
          b.setAttribute("aria-disabled", "true");
        }
      });
    };
    const versionValue = (v) => {
      const t = String(v || "").trim();
      if (/^\d+$/.test(t)) return Number(t);
      return t;
    };
    const cmpVersion = (a, b) => {
      const A = versionValue(a),
        B = versionValue(b);
      if (typeof A === "number" && typeof B === "number")
        return A === B ? 0 : A > B ? 1 : -1;
      return String(A).localeCompare(String(B), "pt-BR", {
        numeric: true,
        sensitivity: "base",
      });
    };
    const run = async () => {
      if (!navigator.onLine) return;
      let data = null;
      try {
        const res = await fetch("/version.json?ts=" + Date.now(), {
          cache: "no-store",
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        if (!res.ok) return;
        data = await res.json();
      } catch (_) {
        return;
      }
      const latest = String(
        data?.generated_at_unix || data?.asset_version || data?.version || "",
      );
      if (!latest || !current || cmpVersion(latest, current) <= 0) return;
      const pill = ensureGate();
      pill.hidden = false;
      setLoginEnabled(false);
      setTimeout(() => {
        if (sessionStorage.getItem("prontoo-version-reloaded") !== latest) {
          sessionStorage.setItem("prontoo-version-reloaded", latest);
          location.reload();
          return;
        }
        setLoginEnabled(true);
        pill.classList.add("is-done");
        pill.innerHTML =
          '<span class="material-symbols-rounded" aria-hidden="true">check_circle</span><span>Atualização recebida</span>';
        setTimeout(() => {
          pill.hidden = true;
          pill.classList.remove("is-done");
        }, 1200);
      }, 220);
    };
    setTimeout(run, 250);
    w.addEventListener("online", run);
  }
  function initOnboardingWizard(root = d) {
    $$("[data-onboarding-wizard]", root).forEach((w) => {
      if (w.dataset.onboardingWizardReady) return;
      w.dataset.onboardingWizardReady = "1";
      const form = $("form", w),
        steps = $$("[data-wizard-step]", w),
        dots = $$(".wizard-progress span", w),
        prev = $("[data-wizard-prev]", w),
        next = $("[data-wizard-next]", w),
        submit = $("[data-wizard-submit]", w);
      if (!form || !steps.length) return;
      let index = steps.findIndex((s) => s.classList.contains("is-active"));
      if (index < 0) index = 0;
      const show = (i) => {
        index = Math.max(0, Math.min(steps.length - 1, i));
        steps.forEach((s, n) => {
          const on = n === index;
          s.hidden = !on;
          s.classList.toggle("is-active", on);
        });
        dots.forEach((dot, n) => {
          dot.classList.toggle("is-active", n === index);
          dot.classList.toggle("is-done", n < index);
        });
        if (prev) prev.hidden = index === 0;
        if (next) next.hidden = index === steps.length - 1;
        if (submit) submit.hidden = index !== steps.length - 1;
        try {
          w.scrollIntoView({ behavior: "smooth", block: "start" });
        } catch (_) {}
      };
      const controls = (step) =>
        $$("input,select,textarea", step).filter(
          (el) => !el.disabled && el.type !== "hidden",
        );
      const validateStep = (step) => {
        for (const el of controls(step)) {
          if (!el.checkValidity()) {
            try {
              el.reportValidity();
            } catch (_) {
              el.focus();
            }
            return false;
          }
        }
        return true;
      };
      prev && prev.addEventListener("click", () => show(index - 1));
      next &&
        next.addEventListener("click", () => {
          if (validateStep(steps[index])) show(index + 1);
        });
      const profession = $('[name="responsible_profession"]', form),
        professionTargets = $$("[data-onboarding-profession]", w);
      const syncProfession = () => {
        if (!profession || !professionTargets.length) return;
        const opt =
          profession.options && profession.selectedIndex >= 0
            ? profession.options[profession.selectedIndex]
            : null;
        const txt =
          (
            (opt && opt.textContent) ||
            profession.value ||
            "Profissional"
          ).trim() || "Profissional";
        professionTargets.forEach((el) => {
          el.textContent = txt;
        });
      };
      profession && profession.addEventListener("change", syncProfession);
      syncProfession();
      form.addEventListener("submit", (e) => {
        const invalid = $$("input,select,textarea", form).find(
          (el) => !el.disabled && el.type !== "hidden" && !el.checkValidity(),
        );
        if (invalid) {
          const step = invalid.closest("[data-wizard-step]");
          const pos = steps.indexOf(step);
          if (pos >= 0) show(pos);
          e.preventDefault();
          setTimeout(() => {
            try {
              invalid.reportValidity();
            } catch (_) {
              invalid.focus();
            }
          }, 40);
        }
      });
      show(index);
    });
  }
  function initDeviceFingerprint(root = d) {
    const forms = $$("form[data-login-form]", root);
    if (!forms.length) return;
    const key = "prontoo-device-seed";
    const bytesHex = () => {
      try {
        const a = new Uint8Array(24);
        crypto.getRandomValues(a);
        return Array.from(a, (b) => b.toString(16).padStart(2, "0")).join("");
      } catch (_) {
        return String(Date.now()) + "-" + Math.random().toString(16).slice(2);
      }
    };
    let seed = "";
    try {
      seed = stableGet(key);
      if (!seed) {
        seed = bytesHex();
        stableSet(key, seed);
      }
    } catch (_) {
      seed = bytesHex();
    }
    const tz = (() => {
      try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || "";
      } catch (_) {
        return "";
      }
    })();
    const mode =
      (navigator.maxTouchPoints || 0) > 1 &&
      Math.min(w.innerWidth || 9999, w.screen?.width || 9999) <= 980
        ? "mobile_browser"
        : "browser";
    const meta = {
      seed,
      ua: navigator.userAgent || "",
      platform: navigator.platform || "",
      vendor: navigator.vendor || "",
      language: navigator.language || "",
      languages: Array.isArray(navigator.languages)
        ? navigator.languages.slice(0, 4)
        : [],
      timezone: tz,
      screen: [
        screen?.width || 0,
        screen?.height || 0,
        screen?.colorDepth || 0,
      ].join("x"),
      memory: navigator.deviceMemory || "",
      cores: navigator.hardwareConcurrency || "",
      touch: navigator.maxTouchPoints || 0,
      mode,
      version:
        d.body?.dataset.appVersion ||
        $('meta[name="prontoo-version"]')?.content ||
        "",
    };
    const source = Object.keys(meta)
      .sort()
      .map(
        (k) => k + ":" + (Array.isArray(meta[k]) ? meta[k].join(",") : meta[k]),
      )
      .join("|");
    const fallbackHash = (str) => {
      let h1 = 0x811c9dc5,
        h2 = 0x45d9f3b;
      for (let i = 0; i < str.length; i++) {
        const c = str.charCodeAt(i);
        h1 ^= c;
        h1 = Math.imul(h1, 16777619);
        h2 ^= c;
        h2 = Math.imul(h2, 1597334677);
      }
      const x =
        (h1 >>> 0).toString(16).padStart(8, "0") +
        (h2 >>> 0).toString(16).padStart(8, "0");
      return (x + x + x + x).slice(0, 64);
    };
    const label = [
      navigator.platform || "Dispositivo",
      screen?.width && screen?.height ? `${screen.width}×${screen.height}` : "",
      tz,
    ]
      .filter(Boolean)
      .join(" · ")
      .slice(0, 160);
    const fill = (hash) =>
      forms.forEach((f) => {
        const h = $("[data-device-hash]", f),
          l = $("[data-device-label]", f),
          p = $("[data-device-platform]", f),
          m = $("[data-device-meta]", f);
        if (h) h.value = hash;
        if (l) l.value = label;
        if (p) p.value = String(navigator.platform || "").slice(0, 120);
        if (m) {
          try {
            m.value = JSON.stringify(meta).slice(0, 1200);
          } catch (_) {
            m.value = "";
          }
        }
      });
    fill(fallbackHash(source));
    if (crypto?.subtle) {
      crypto.subtle
        .digest("SHA-256", new TextEncoder().encode(source))
        .then((buf) => {
          const hex = Array.from(new Uint8Array(buf), (b) =>
            b.toString(16).padStart(2, "0"),
          ).join("");
          fill(hex);
        })
        .catch(() => {});
    }
  }
  function initLoginAutotest(root = d) {
    const forms = $$("form[data-login-autotest]", root);
    if (!forms.length || w.__prontooLoginAutotestReady) return;
    w.__prontooLoginAutotestReady = true;
    const boot = $("[data-login-boot]", root),
      status = $("[data-login-boot-status]", root),
      bootIcon = $("[data-login-boot-icon]", root),
      brandmark = $("[data-login-brandmark]", root);
    const loginIconMarkup = (name) =>
      '<span class="material-symbols-rounded" aria-hidden="true">' +
      name +
      "</span>";
    const setBootIcon = (name) => {
      if (bootIcon) bootIcon.innerHTML = loginIconMarkup(name);
    };
    const setBrandIcon = (name) => {};
    const setStatus = (txt, ok = null) => {
      if (status) status.textContent = txt;
      if (boot) {
        boot.classList.toggle("is-ok", ok === true);
        boot.classList.toggle("is-bad", ok === false);
      }
      if (ok === true) {
        setBootIcon(
          txt === "Você já pode entrar agora."
            ? "verified_user"
            : "check_circle",
        );
        setBrandIcon(
          (brandmark && brandmark.dataset.loginSecureIcon) || "verified_user",
        );
      } else if (ok === false) {
        setBootIcon("error");
        setBrandIcon(
          (brandmark && brandmark.dataset.loginDefaultIcon) || "home_health",
        );
      } else {
        setBootIcon("sync");
        setBrandIcon(
          (brandmark && brandmark.dataset.loginDefaultIcon) || "home_health",
        );
      }
    };
    const sync = () =>
      forms.forEach((f) => {
        const cpf = $("[data-login-cpf]", f),
          pass = $("[data-login-password]", f),
          code = $("[data-login-code]", f),
          btn = $("[data-login-submit]", f);
        if (!btn) return;
        const mfaStage = f.dataset.loginStage === "mfa";
        const bootReady =
          f.dataset.autotestReady === "1" && f.dataset.loginLocked !== "1";
        const ready =
          bootReady &&
          String(cpf?.value || "").trim().length > 0 &&
          String(mfaStage ? code?.value || "" : pass?.value || "").trim()
            .length > 0;
        if (ready) {
          btn.disabled = false;
          btn.removeAttribute("aria-disabled");
          btn.innerHTML =
            loginIconMarkup(mfaStage ? "verified_user" : "login") +
            "<span>" +
            (mfaStage ? "Confirmar e entrar" : "Entrar") +
            "</span>";
        } else {
          btn.disabled = true;
          btn.setAttribute("aria-disabled", "true");
          btn.innerHTML =
            loginIconMarkup(
              bootReady
                ? mfaStage
                  ? "verified_user"
                  : "login"
                : "hourglass_top",
            ) +
            "<span>" +
            (mfaStage ? "Confirmar e entrar" : "Entrar") +
            "</span>";
        }
      });
    w.prontooSyncLoginButton = sync;
    forms.forEach((f) => {
      ["input", "change"].forEach((ev) => f.addEventListener(ev, sync));
      sync();
    });
    const run = async () => {
      setStatus("Deixando tudo pronto para você...");
      try {
        await new Promise((r) => setTimeout(r, 850));
        const res = await fetch("/?r=login_autotest&_=" + Date.now(), {
          cache: "no-store",
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        const data = await res.json();
        if (data?.redirect) {
          setStatus("Abrindo...", true);
          location.href = data.redirect;
          return;
        }
        forms.forEach((f) => (f.dataset.autotestReady = data?.ok ? "1" : "0"));
        if (data?.ok) {
          setStatus("Você já pode entrar agora.", true);
        } else {
          setStatus(
            "Entrada indisponível no momento. Tente novamente em instantes.",
            false,
          );
        }
      } catch (_) {
        forms.forEach((f) => (f.dataset.autotestReady = "0"));
        setStatus(
          "Entrada indisponível no momento. Tente novamente em instantes.",
          false,
        );
      } finally {
        sync();
      }
    };
    run();
  }
  function initLoginMfaFlow(root = d) {
    const forms = $$("form[data-login-form]", root);
    if (!forms.length) return;
    const iconMarkup = (name) =>
      '<span class="material-symbols-rounded" aria-hidden="true">' +
      name +
      "</span>";
    forms.forEach((form) => {
      if (form.dataset.loginMfaFlowReady === "1") return;
      form.dataset.loginMfaFlowReady = "1";
      const boot = $("[data-login-boot]", root),
        status = $("[data-login-boot-status]", root),
        bootIcon = $("[data-login-boot-icon]", root),
        submit = $("[data-login-submit]", form),
        cpf = $("[data-login-cpf]", form);
      let retryTimer = 0;
      const setStatus = (message, state = null) => {
        if (status) status.textContent = String(message || "");
        if (bootIcon) {
          bootIcon.innerHTML = iconMarkup(
            state === true ? "verified_user" : state === false ? "error" : "sync",
          );
        }
        if (boot) {
          boot.classList.toggle("is-ok", state === true);
          boot.classList.toggle("is-bad", state === false);
        }
      };
      const sync = () => {
        if (w.prontooSyncLoginButton) {
          w.prontooSyncLoginButton();
          return;
        }
        if (!submit) return;
        const mfaStage = form.dataset.loginStage === "mfa";
        const credential = mfaStage
          ? $("[data-login-code]", form)
          : $("[data-login-password]", form);
        const ready =
          form.dataset.autotestReady === "1" &&
          form.dataset.loginLocked !== "1" &&
          String(cpf?.value || "").trim() !== "" &&
          String(credential?.value || "").trim() !== "";
        submit.disabled = !ready;
        submit.setAttribute("aria-disabled", ready ? "false" : "true");
        if (ready) submit.removeAttribute("aria-disabled");
      };
      const setBusy = (busy) => {
        form.classList.toggle("is-submitting", busy);
        if (!submit) return;
        submit.disabled = busy;
        submit.setAttribute("aria-disabled", busy ? "true" : "false");
        if (busy) {
          submit.innerHTML =
            iconMarkup("progress_activity") + "<span>Confirmando...</span>";
        } else {
          sync();
        }
      };
      const setRetry = (seconds, message) => {
        clearInterval(retryTimer);
        let remaining = Math.max(1, Number(seconds) || 1);
        form.dataset.loginLocked = "1";
        const tick = () => {
          setStatus(
            message +
              " Tente novamente em " +
              remaining +
              (remaining === 1 ? " segundo." : " segundos."),
            false,
          );
          remaining -= 1;
          if (remaining >= 0) return;
          clearInterval(retryTimer);
          retryTimer = 0;
          form.dataset.loginLocked = "0";
          setStatus(
            form.dataset.loginStage === "mfa"
              ? "Digite um novo código de verificação."
              : "Você já pode tentar entrar novamente.",
            true,
          );
          sync();
        };
        tick();
        retryTimer = w.setInterval(tick, 1000);
      };
      const updateCsrf = (value) => {
        if (!value) return;
        const csrf = $('[name="csrf"]', form);
        if (csrf) csrf.value = String(value);
        const meta = $('meta[name="csrf-token"]');
        if (meta) meta.content = String(value);
      };
      const enterMfaStage = (data) => {
        form.dataset.loginStage = "mfa";
        form.dataset.autotestReady = "1";
        form.dataset.loginLocked = "0";
        if (cpf) {
          cpf.readOnly = true;
          cpf.setAttribute("aria-readonly", "true");
        }
        const password = $("[data-login-password]", form);
        const row = password?.closest(".field");
        if (!row) throw new Error("Campo de senha não encontrado.");
        row.innerHTML =
          '<span>Código de verificação</span><input name="code" type="text" value="" required autocomplete="one-time-code" maxlength="16" placeholder="Digite o código" autocapitalize="characters" spellcheck="false" aria-describedby="login-verification-help" data-login-code>';
        let act = $("[data-login-act]", form);
        if (!act) {
          act = d.createElement("input");
          act.type = "hidden";
          act.name = "act";
          act.dataset.loginAct = "";
          form.insertBefore(act, row);
        }
        act.value = "mfa_verify";
        let help = $("[data-login-mfa-help]", form);
        if (!help) {
          help = d.createElement("small");
          help.className = "field-help login-mfa-help";
          help.dataset.loginMfaHelp = "";
          help.id = "login-verification-help";
          help.textContent =
            "Abra seu aplicativo autenticador e digite o código exibido. Você também pode usar um código de recuperação.";
          row.insertAdjacentElement("afterend", help);
        }
        updateCsrf(data?.csrf);
        setStatus(
          data?.message ||
            "Senha confirmada. Agora, digite o código do aplicativo.",
          true,
        );
        sync();
        const code = $("[data-login-code]", form);
        if (code) {
          ["input", "change"].forEach((eventName) =>
            code.addEventListener(eventName, sync),
          );
          try {
            code.focus();
          } catch (_) {}
        }
      };
      ["input", "change"].forEach((eventName) =>
        form.addEventListener(eventName, sync),
      );
      form.addEventListener("submit", async (event) => {
        if (form.dataset.loginLocked === "1") {
          event.preventDefault();
          return;
        }
        if (!form.checkValidity()) return;
        event.preventDefault();
        setBusy(true);
        setStatus(
          form.dataset.loginStage === "mfa"
            ? "Conferindo o código..."
            : "Confirmando CPF e senha...",
        );
        try {
          const response = await fetch(form.action || location.href, {
            method: "POST",
            body: new FormData(form),
            cache: "no-store",
            credentials: "same-origin",
            headers: {
              Accept: "application/json",
              "X-Requested-With": "XMLHttpRequest",
            },
            redirect: "error",
          });
          const contentType = response.headers.get("content-type") || "";
          if (!contentType.includes("application/json")) {
            throw new Error("Resposta de autenticação inválida.");
          }
          const data = await response.json();
          updateCsrf(data?.csrf);
          if (data?.ok === true && data?.stage === "mfa") {
            enterMfaStage(data);
            return;
          }
          if (data?.ok === true && data?.redirect) {
            setStatus("Acesso confirmado. Abrindo o Prontoo...", true);
            location.assign(data.redirect);
            return;
          }
          if (data?.reset) {
            setStatus(
              data?.message ||
                "A confirmação expirou. Informe novamente o CPF e a senha.",
              false,
            );
            w.setTimeout(() => location.replace("/?r=login&relogin=1"), 900);
            return;
          }
          const message =
            data?.message || "Não foi possível confirmar o acesso.";
          if (Number(data?.retry_after) > 0) {
            setRetry(Number(data.retry_after), message);
          } else {
            setStatus(message, false);
          }
        } catch (_) {
          setStatus(
            "Não foi possível confirmar o acesso agora. Tente novamente.",
            false,
          );
        } finally {
          setBusy(false);
        }
      });
      if (form.dataset.loginStage === "mfa") {
        setStatus(
          "Senha confirmada. Agora, digite o código do aplicativo.",
          true,
        );
      }
      sync();
    });
  }
  function initMonthlyGoal(root = d) {
    const cards = $$("[data-monthly-goal-card]", root);
    if (!cards.length) return;
    const update = async () => {
      try {
        const res = await fetch("/?r=goal_status&_=" + Date.now(), {
          cache: "no-store",
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        if (!res.ok) return;
        const data = await res.json();
        if (!data || data.ok !== true) return;
        cards.forEach((card) => {
          if (!data.share) {
            card.hidden = true;
            return;
          }
          card.hidden = false;
          const pct = $("[data-goal-percent]", card),
            bar = $("[data-goal-bar]", card);
          const pctText =
            String(Math.round(Number(data.percent) || 0)).replace(".", ",") +
            "%";
          if (pct) pct.textContent = pctText;
          if (bar) bar.style.width = (data.bar || 0) + "%";
          card.setAttribute("aria-label", "Meta mensal: " + pctText);
          card.removeAttribute("title");
        });
      } catch (_) {}
    };
    const schedule = () => {
      const now = new Date();
      const next = new Date(now);
      next.setHours(now.getHours() + 1, 0, 5, 0);
      setTimeout(
        () => {
          update();
          schedule();
        },
        Math.max(60000, next - now),
      );
    };
    schedule();
  }

  function initFooterTelemetryLines(root = d) {
    const wrap = $("[data-footer-telemetry-lines]", root);
    if (!wrap) return;
    wrap.dataset.footerTelemetryLinesReady = "1";
  }
  function initAdminGlobalChartsRefresh(root = d) {
    const wrap = $("[data-admin-global-charts]", root);
    if (!wrap || wrap.dataset.adminChartRefreshReady) return;
    wrap.dataset.adminChartRefreshReady = "1";
    const interval = Math.max(60000, Number(wrap.dataset.refreshMs) || 900000);
    let last = Date.now();
    const refresh = () => {
      if (d.visibilityState && d.visibilityState !== "visible") return;
      if (Date.now() - last < interval) return;
      last = Date.now();
      try {
        location.reload();
      } catch (_) {
        location.href = location.href;
      }
    };
    setInterval(refresh, 60000);
    d.addEventListener("visibilitychange", refresh);
  }
  function initPatientCepLookup(root = d) {
    $$("input[data-patient-cep]", root).forEach((i) => {
      if (i.dataset.patientCepReady) return;
      i.dataset.patientCepReady = "1";
      bindMask(
        i,
        (v) => {
          const x = digits(v).slice(0, 8);
          return x.length > 5 ? x.replace(/(\d{5})(\d{0,3})/, "$1-$2") : x;
        },
        9,
        "numeric",
        "postal-code",
      );
      let last = "";
      const form = () => i.form || i.closest("form");
      const set = (name, val) => {
        const f = form();
        const el = f && $(`[name="${esc(name)}"]`, f);
        if (!el || val == null) return;
        el.value = String(val || "");
        el.dataset.cepAutoFilled = "1";
        el.dispatchEvent(new Event("input", { bubbles: true }));
        el.dispatchEvent(new Event("change", { bubbles: true }));
      };
      const setSelectCity = (city, uf, ibge) => {
        const f = form();
        if (!f) return;
        const st = $("[data-br-state]", f),
          ct = $("[data-br-city]", f),
          hid = $("[data-br-city-ibge]", f);
        if (st && uf && st.value !== uf) {
          st.value = uf;
          st.dispatchEvent(new Event("change", { bubbles: true }));
        }
        if (ct && city) {
          ct.dataset.selectedCity = city;
          const choose = () => {
            let found = false;
            Array.from(ct.options || []).forEach((o) => {
              if (
                (o.value || "").toLowerCase() === String(city).toLowerCase()
              ) {
                o.selected = true;
                found = true;
                if (hid) hid.value = o.dataset.ibge || ibge || "";
              }
            });
            if (!found) {
              const o = d.createElement("option");
              o.value = city;
              o.textContent = city;
              o.dataset.ibge = ibge || "";
              o.selected = true;
              ct.appendChild(o);
              if (hid) hid.value = ibge || "";
            }
            ct.dispatchEvent(new Event("change", { bubbles: true }));
          };
          setTimeout(choose, 450);
          setTimeout(choose, 1100);
        } else if (hid && ibge) hid.value = ibge;
      };
      const hint = d.createElement("small");
      hint.className = "field-hint patient-cep-hint";
      hint.setAttribute("aria-live", "polite");
      i.insertAdjacentElement("afterend", hint);
      const run = () => {
        const cep = digits(i.value);
        if (cep.length !== 8) {
          hint.textContent = "";
          return;
        }
        if (cep === last) return;
        last = cep;
        hint.textContent = "Buscando endereço pelo CEP...";
        fetch(`https://viacep.com.br/ws/${cep}/json/`, { cache: "no-store" })
          .then((r) => (r.ok ? r.json() : Promise.reject()))
          .then((j) => {
            if (!j || j.erro) {
              hint.textContent =
                "CEP não encontrado. Preencha o endereço manualmente.";
              return;
            }
            set("address", j.logradouro || "");
            set("address_neighborhood", j.bairro || "");
            set("address_complement", j.complemento || "");
            setSelectCity(j.localidade || "", j.uf || "", j.ibge || "");
            hint.textContent =
              "Endereço preenchido pelo CEP. Confira número e complemento.";
            const f = form(),
              num = f && $('[name="address_number"]', f);
            if (num && !num.value)
              try {
                num.focus();
              } catch (_) {}
          })
          .catch(() => {
            hint.textContent =
              "Não foi possível consultar o CEP agora. Preencha manualmente.";
          });
      };
      i.addEventListener("blur", run);
      i.addEventListener("change", run);
      i.addEventListener(
        "input",
        () => {
          if (digits(i.value).length === 8) run();
          else hint.textContent = "";
        },
        { passive: true },
      );
    });
  }
  function init(root = d) {
    initFooterTelemetryLines(root);
    initAdminGlobalChartsRefresh(root);
    initDeviceFingerprint(root);
    initVersionGate(root);
    initLoginAutotest(root);
    initLoginMfaFlow(root);
    initLoginScrollLock();
    initOnboardingWizard(root);
    initMonthlyGoal(root);
    initMasks(root);
    initCpfValidation(root);
    initPanels(root);
    initTabs(root);
    initOmit(root);
    initCities(root);
    initPatientCepLookup(root);
    initClock();
    initCountdown();
    initPersonAutosuggest(root);
    initPersonCpfLookup(root);
    initPatientCpfLookup(root);
    initPatientDocumentSuggest(root);
    initCounterpartyDocumentLookup(root);
    initCounterpartyDocumentSuggest(root);
    initPatientLiveSearch(root);
    initProcedureSelects(root);
    initAppointmentPayments(root);
    initTaskTargetFields(root);
    initPasswordStrength(root);
    initDocumentEditors(root);
    initClinicVisualPicker(root);
  }
  d.addEventListener("click", (e) => {
    const tab = closest(
      e.target,
      '.patient-tab-nav label,.patient-tab-nav [role="tab"],.patient-tab-nav [data-tab-target]',
    );
    if (tab) {
      e.preventDefault();
      openTabFrom(tab);
      return;
    }
    const close = closest(e.target, "[data-close-panel]");
    if (close) {
      e.preventDefault();
      closePanel(close);
      return;
    }
    if (closest(e.target, "[data-print-page]")) {
      e.preventDefault();
      w.print();
    }
  });
  d.addEventListener("keydown", (e) => {
    const tab = closest(
      e.target,
      '.patient-tab-nav label,.patient-tab-nav [role="tab"],.patient-tab-nav [data-tab-target]',
    );
    if (!tab) return;
    const tabs = $$(
      '.patient-tab-nav label,.patient-tab-nav [role="tab"],.patient-tab-nav [data-tab-target]',
      tab.closest(".patient-tabs-shell"),
    );
    const i = tabs.indexOf(tab);
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      openTabFrom(tab);
    } else if (e.key === "ArrowDown" || e.key === "ArrowRight") {
      e.preventDefault();
      openTabFrom(tabs[(i + 1) % tabs.length]);
    } else if (e.key === "ArrowUp" || e.key === "ArrowLeft") {
      e.preventDefault();
      openTabFrom(tabs[(i - 1 + tabs.length) % tabs.length]);
    }
  });
  d.addEventListener("change", (e) => {
    const t = e.target;
    if (t.matches?.(".patient-tab-radio"))
      activateTabs(
        t.closest(".patient-tabs-shell"),
        t.dataset.tabKey || tabKey(t.id),
      );
  });
  d.addEventListener(
    "toggle",
    (e) => {
      if (e.target.matches?.("details")) setTimeout(updatePanels, 0);
    },
    true,
  );
  d.addEventListener("submit", (e) => {
    const form = e.target;
    if (!validateCpfFields(e.target)) {
      e.preventDefault();
      e.stopPropagation();
      return;
    }
    if (
      form.matches?.("[data-submit-once]") &&
      form.dataset.submitting === "1"
    ) {
      e.preventDefault();
      e.stopPropagation();
      return;
    }
    $$("[data-doc-editor-wrap]", form).forEach(syncDocumentEditor);
    if (e.defaultPrevented) return;
    if (form.matches?.("[data-submit-once]")) {
      form.dataset.submitting = "1";
      setTimeout(() => {
        $$('button[type="submit"],input[type="submit"]', form).forEach(
          (button) => {
            button.disabled = true;
            button.setAttribute("aria-disabled", "true");
          },
        );
      }, 0);
    }
    const b = form.querySelector(
      'button[type="submit"],button:not([type])',
    );
    if (b && !b.disabled && !b.dataset.loginSubmit)
      b.setAttribute("aria-busy", "true");
  });
  d.readyState === "loading"
    ? d.addEventListener("DOMContentLoaded", () => init())
    : init();
})();
document.addEventListener("change", function (ev) {
  const paid = ev.target.closest("[data-payment-paid-toggle]");
  if (paid) {
    const form = paid.closest("[data-subscription-payment-form]");
    const fields = form
      ? form.querySelector("[data-payment-paid-fields]")
      : null;
    const own = form ? form.querySelector("[data-own-account-toggle]") : null;
    const holder = form ? form.querySelector("[data-holder-field]") : null;
    const input = holder ? holder.querySelector("input") : null;
    if (fields) fields.hidden = !paid.checked;
    if (holder) holder.hidden = !!(own && own.checked);
    if (input) input.required = !!(paid.checked && !(own && own.checked));
  }
  const own = ev.target.closest("[data-own-account-toggle]");
  if (own) {
    const form = own.closest("[data-subscription-payment-form]");
    const paidBox = form
      ? form.querySelector("[data-payment-paid-toggle]")
      : null;
    const holder = form ? form.querySelector("[data-holder-field]") : null;
    const input = holder ? holder.querySelector("input") : null;
    if (holder) holder.hidden = own.checked;
    if (input) input.required = !!(paidBox && paidBox.checked && !own.checked);
  }
});
(function () {
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      try {
        var ta = document.createElement("textarea");
        ta.value = text;
        ta.setAttribute("readonly", "readonly");
        ta.style.position = "fixed";
        ta.style.left = "-9999px";
        ta.style.top = "-9999px";
        document.body.appendChild(ta);
        ta.select();
        var ok = document.execCommand("copy");
        document.body.removeChild(ta);
        ok ? resolve() : reject(new Error("copy failed"));
      } catch (e) {
        reject(e);
      }
    });
  }
  document.addEventListener(
    "click",
    function (ev) {
      var box =
        ev.target && ev.target.closest
          ? ev.target.closest("[data-copy-pix-key]")
          : null;
      if (!box) return;
      ev.preventDefault();
      var value = box.getAttribute("data-pix-value") || "pix@prontoo.app";
      var status = box.querySelector("[data-pix-copy-status]");
      copyText(value)
        .then(function () {
          if (status) {
            status.textContent = "Chave Pix copiada.";
          }
          box.classList.add("copied");
          window.setTimeout(function () {
            if (status) status.textContent = "";
            box.classList.remove("copied");
          }, 2600);
        })
        .catch(function () {
          if (status) {
            status.textContent = "Não foi possível copiar automaticamente.";
          }
        });
    },
    false,
  );
})();
document.addEventListener(
  "submit",
  function (e) {
    var f = e.target;
    if (
      f &&
      f.matches &&
      f.matches("form") &&
      typeof validateCpfFields === "function" &&
      !validateCpfFields(f)
    )
      e.preventDefault();
  },
  true,
);
(function () {
  if (!document.body || !document.body.classList.contains("is-read-only"))
    return;
  function isSupportForm(form) {
    var act = form.querySelector('input[name="act"][value="support_message"]');
    return !!act;
  }
  function isEnvironmentSwitchForm(form) {
    var act = form.querySelector(
      'input[name="act"][value="profile_switch_environment"]',
    );
    return !!act;
  }
  function isSearchForm(form) {
    var method = (form.getAttribute("method") || "get").toLowerCase();
    if (method !== "post") return true;
    if (
      form.matches(
        "[data-search-form],.search-form,.quick-search,.patient-search-form,.lead-search-form,.people-search-form,.activity-filter-form,.agenda-filter-form",
      )
    )
      return true;
    if (
      form.querySelector(
        'input[type="search"],input[name="q"],input[name="search"],input[name="query"]',
      )
    )
      return true;
    return false;
  }
  function lockButtonIcon(btn) {
    if (!btn || btn.dataset.readonlyLockedIcon === "1") return;
    btn.dataset.readonlyLockedIcon = "1";
    var ico = btn.querySelector(".material-symbols-rounded,.pt-icon-glyph");
    if (!ico) {
      ico = document.createElement("span");
      ico.className = "material-symbols-rounded pt-icon-glyph";
      ico.setAttribute("aria-hidden", "true");
      btn.insertBefore(ico, btn.firstChild);
    }
    ico.textContent = "lock";
    btn.setAttribute(
      "title",
      btn.getAttribute("title") ||
        "Função bloqueada até a regularização da assinatura.",
    );
    btn.setAttribute(
      "aria-label",
      btn.getAttribute("aria-label") || btn.textContent.trim() + " bloqueado",
    );
  }
  document.querySelectorAll("form").forEach(function (form) {
    if (
      form.matches(".logout,[data-subscription-payment-form]") ||
      isSupportForm(form) ||
      isEnvironmentSwitchForm(form) ||
      isSearchForm(form)
    )
      return;
    form.classList.add("read-only-locked-form");
    form
      .querySelectorAll("input,select,textarea,button")
      .forEach(function (el) {
        var type = (el.getAttribute("type") || "").toLowerCase();
        if (type === "hidden") return;
        if (el.tagName && el.tagName.toLowerCase() === "button")
          lockButtonIcon(el);
        el.disabled = true;
      });
  });
})();
(function () {
  function ready(fn) {
    if (document.readyState === "loading")
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    else fn();
  }
  function focusFirst(step) {
    var el =
      step &&
      step.querySelector(
        'input:not([type="hidden"]),select,textarea,button,a[href]',
      );
    if (el && el.focus)
      window.setTimeout(function () {
        el.focus({ preventScroll: true });
      }, 40);
  }
  function initSignupSteps() {
    var form = document.querySelector("[data-signup-steps]");
    if (!form || form.dataset.signupReady === "1") return;
    form.dataset.signupReady = "1";
    var card = form.closest(".signup-steps-card") || form;
    var steps = Array.prototype.slice.call(
      form.querySelectorAll("[data-signup-step]"),
    );
    var indicators = Array.prototype.slice.call(
      card.querySelectorAll("[data-signup-indicator]"),
    );
    if (!steps.length) return;
    var current = 0;
    var reason = form.querySelector("[data-signup-step-reason]");
    if (!reason) {
      reason = document.createElement("div");
      reason.className = "signup-step-reason";
      reason.setAttribute("data-signup-step-reason", "");
      reason.setAttribute("role", "alert");
      reason.hidden = true;
      form.insertBefore(reason, form.firstChild);
    }
    function fieldLabel(el) {
      var label = el && el.closest ? el.closest("label.field") : null;
      var span = label ? label.querySelector(":scope > span") : null;
      return span && span.textContent ? span.textContent.trim() : "Este campo";
    }
    function explainBlockedStep(el) {
      if (!reason) return;
      var msg =
        (el && (el.dataset.requiredMessage || el.validationMessage)) ||
        "Revise os dados obrigatórios antes de avançar.";
      reason.textContent = fieldLabel(el) + ": " + msg;
      reason.hidden = false;
      reason.classList.add("is-visible");
    }
    function clearBlockedStep() {
      if (!reason) return;
      reason.textContent = "";
      reason.hidden = true;
      reason.classList.remove("is-visible");
    }
    function show(index, focus) {
      current = Math.max(0, Math.min(index, steps.length - 1));
      clearBlockedStep();
      steps.forEach(function (step, i) {
        var active = i === current;
        step.hidden = !active;
        step.classList.toggle("is-active", active);
        step.setAttribute("aria-hidden", active ? "false" : "true");
      });
      indicators.forEach(function (item, i) {
        var active = i === current;
        item.classList.toggle("is-active", active);
        item.classList.toggle("is-complete", i < current);
        item.setAttribute("aria-current", active ? "step" : "false");
      });
      if (focus) focusFirst(steps[current]);
      try {
        card.scrollIntoView({ block: "start", behavior: "smooth" });
      } catch (_) {}
    }
    function validateStep(index) {
      var step = steps[index];
      if (!step) return true;
      var fields = Array.prototype.slice.call(
        step.querySelectorAll("input,select,textarea"),
      );
      for (var i = 0; i < fields.length; i++) {
        var el = fields[i];
        if (el.disabled || el.type === "hidden") continue;
        if (el.matches && el.matches("[data-signup-trial-accept]")) {
          try {
            el.setCustomValidity(
              !el.checked
                ? el.dataset.requiredMessage ||
                    "Confirme que deseja experimentar sem custo."
                : "",
            );
          } catch (_) {}
        }
        if (!el.checkValidity()) {
          show(index, true);
          explainBlockedStep(el);
          if (el.reportValidity) el.reportValidity();
          return false;
        }
      }
      clearBlockedStep();
      return true;
    }
    form.addEventListener("input", function () {
      clearBlockedStep();
    });
    form.addEventListener("change", function (ev) {
      var el = ev.target;
      if (el && el.matches && el.matches("[data-signup-trial-accept]")) {
        try {
          el.setCustomValidity(
            el.checked
              ? ""
              : el.dataset.requiredMessage ||
                  "Confirme que deseja experimentar sem custo.",
          );
        } catch (_) {}
      }
    });
    form.addEventListener("click", function (ev) {
      var next =
        ev.target && ev.target.closest
          ? ev.target.closest("[data-signup-next]")
          : null;
      var prev =
        ev.target && ev.target.closest
          ? ev.target.closest("[data-signup-prev]")
          : null;
      if (next) {
        ev.preventDefault();
        if (validateStep(current)) show(current + 1, true);
      }
      if (prev) {
        ev.preventDefault();
        show(current - 1, true);
      }
    });
    form.addEventListener(
      "submit",
      function (ev) {
        for (var i = 0; i < steps.length; i++) {
          if (!validateStep(i)) {
            ev.preventDefault();
            ev.stopPropagation();
            return false;
          }
        }
      },
      true,
    );
    show(0, false);
  }
  ready(initSignupSteps);
})();
(function () {
  function ready(fn) {
    if (document.readyState === "loading")
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    else fn();
  }
  function initNoticeForms() {
    document.querySelectorAll("[data-notice-form]").forEach(function (form) {
      if (form.dataset.noticeFormReady === "1") return;
      form.dataset.noticeFormReady = "1";
      var scope = form.querySelector("[data-notice-target-scope]");
      var target = form.querySelector("[data-notice-target-user]");
      var targetSelect = form.querySelector("[data-notice-target-user-select]");
      function sync() {
        var user = scope && scope.value === "user";
        if (target) target.hidden = !user;
        if (targetSelect) targetSelect.required = !!user;
      }
      if (scope) scope.addEventListener("change", sync);
      sync();
    });
  }
  ready(initNoticeForms);
})();
(function () {
  function ready(fn) {
    if (document.readyState === "loading")
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    else fn();
  }
  function leadDigits(v) {
    return String(v || "")
      .replace(/\D+/g, "")
      .slice(0, 11);
  }
  function field(form, name) {
    return form.querySelector('[name="' + name + '"]');
  }
  function setValue(form, name, value, force) {
    var el = field(form, name);
    if (!el) return;
    if (force || !String(el.value || "").trim()) {
      el.value = String(value || "");
      el.dispatchEvent(new Event("input", { bubbles: true }));
      el.dispatchEvent(new Event("change", { bubbles: true }));
    }
  }
  function initLeadConvertLookup() {
    document
      .querySelectorAll("[data-lead-convert-form]")
      .forEach(function (form) {
        if (form.dataset.leadConvertLookupReady === "1") return;
        form.dataset.leadConvertLookupReady = "1";
        var cpf = form.querySelector("[data-lead-patient-cpf-lookup]");
        var status = form.querySelector("[data-lead-convert-status]");
        if (!cpf) return;
        var timer = null,
          last = "";
        function setStatus(text, cls, openUrl) {
          if (!status) return;
          status.className =
            "lead-convert-existing lead-convert-lookup-status" +
            (cls ? " " + cls : "");
          status.innerHTML = "";
          if (text) {
            var span = document.createElement("span");
            span.textContent = text;
            status.appendChild(span);
          }
          if (openUrl) {
            var a = document.createElement("a");
            a.href = openUrl;
            a.className = "primary small";
            a.innerHTML =
              '<span class="material-symbols-rounded" aria-hidden="true">folder_open</span><span>Abrir Ficha do Paciente</span>';
            status.appendChild(a);
          }
        }
        async function lookup() {
          var raw = leadDigits(cpf.value);
          if (raw.length < 11) {
            last = "";
            setStatus("", "");
            return;
          }
          if (raw === last) return;
          last = raw;
          var url = cpf.getAttribute("data-lead-patient-cpf-lookup") || "";
          if (!url) return;
          setStatus("Verificando CPF...", "is-new");
          try {
            var sep = url.indexOf("?") >= 0 ? "&" : "?";
            var res = await fetch(
              url + sep + "cpf=" + encodeURIComponent(raw),
              {
                cache: "no-store",
                credentials: "same-origin",
                headers: { Accept: "application/json" },
              },
            );
            if (!res.ok) throw new Error("lookup");
            var data = await res.json();
            if (data && data.found) {
              setValue(form, "name", data.name || "", true);
              setValue(form, "birth_date", data.birth_date || "", true);
              if (data.already_patient) {
                setStatus(
                  "Este interessado já era paciente. Ao concluir, o interesse será arquivado.",
                  "is-found",
                  data.open_url || "",
                );
              } else {
                setStatus(
                  data.message ||
                    "Dados encontrados e preenchidos automaticamente.",
                  "is-found",
                );
              }
            } else {
              setStatus(
                (data && data.message) ||
                  "CPF válido. Complete Nome Completo e Nascimento.",
                "is-new",
              );
            }
          } catch (_) {
            setStatus(
              "Não foi possível verificar agora. O sistema conferirá ao concluir.",
              "is-error",
            );
          }
        }
        cpf.addEventListener(
          "input",
          function () {
            clearTimeout(timer);
            timer = setTimeout(lookup, 260);
          },
          { passive: true },
        );
        cpf.addEventListener("blur", lookup, { passive: true });
        if (leadDigits(cpf.value).length === 11) lookup();
      });
  }
  function initLeadCreateLookup() {
    document
      .querySelectorAll("[data-lead-create-form]")
      .forEach(function (form) {
        if (form.dataset.leadLookupReady === "1") return;
        form.dataset.leadLookupReady = "1";
        var phone = form.querySelector("[data-lead-phone-lookup]");
        var status = form.querySelector("[data-lead-lookup-status]");
        if (!phone) return;
        var timer = null,
          last = "";
        function setStatus(text, cls, html) {
          if (!status) return;
          status.className = "lead-lookup-status" + (cls ? " " + cls : "");
          if (html) status.innerHTML = text || "";
          else status.textContent = text || "";
        }
        async function lookup() {
          var digits = leadDigits(phone.value);
          if (digits.length < 10) {
            last = "";
            setStatus("", "");
            return;
          }
          if (digits === last) return;
          last = digits;
          var url = phone.getAttribute("data-lead-phone-lookup") || "";
          if (!url) return;
          setStatus("Verificando histórico do telefone...", "is-new");
          try {
            var sep = url.indexOf("?") >= 0 ? "&" : "?";
            var res = await fetch(
              url + sep + "phone=" + encodeURIComponent(digits),
              {
                cache: "no-store",
                credentials: "same-origin",
                headers: { Accept: "application/json" },
              },
            );
            if (!res.ok) throw new Error("lookup");
            var data = await res.json();
            if (data && data.found) {
              if (data.patient_found) {
                var safeUrl = String(data.open_url || "").replace(
                  /"/g,
                  "&quot;",
                );
                var msg =
                  data.message ||
                  "Paciente já cadastrado localizado pelo telefone.";
                setStatus(
                  msg +
                    (safeUrl
                      ? ' <a class="primary small" href="' +
                        safeUrl +
                        '"><span>Abrir ficha</span></a>'
                      : ""),
                  "is-found",
                  true,
                );
                setValue(form, "phone", data.phone || phone.value, true);
                setValue(form, "name", data.name || "", true);
                return;
              }
              setValue(form, "phone", data.phone || phone.value, true);
              setValue(form, "name", data.name || "", true);
              setValue(form, "source", data.source || "", true);
              setValue(form, "interest", data.interest || "", true);
              setValue(form, "next_action_at", data.next_action_at || "", true);
              setValue(form, "notes", data.notes || "", false);
              var st = field(form, "stage");
              if (
                st &&
                data.stage &&
                Array.prototype.some.call(st.options, function (o) {
                  return o.value === data.stage;
                })
              )
                st.value = data.stage;
              setStatus(
                data.message ||
                  "Telefone já registrado. Continue o histórico deste interessado.",
                "is-found",
              );
            } else {
              setStatus(
                (data && data.message) ||
                  "Telefone sem histórico. Continue o cadastro.",
                "is-new",
              );
            }
          } catch (_) {
            setStatus(
              "Não foi possível verificar o histórico agora. O sistema conferirá ao salvar.",
              "is-error",
            );
          }
        }
        phone.addEventListener(
          "input",
          function () {
            clearTimeout(timer);
            timer = setTimeout(lookup, 380);
          },
          { passive: true },
        );
        phone.addEventListener("blur", lookup, { passive: true });
      });
  }
  function initMaestro() {
    document.querySelectorAll("[data-maestro-form]").forEach(function (form) {
      if (form.dataset.maestroReady === "1") return;
      form.dataset.maestroReady = "1";
      var moduleSelect = form.querySelector("[data-maestro-module]");
      var trigger = form.querySelector("[data-maestro-trigger]");
      var amount = form.querySelector("[data-maestro-amount]");
      var help = form.querySelector("[data-maestro-amount-help]");
      var name = form.querySelector("[data-maestro-name]");
      var title = form.querySelector("[data-maestro-title]");
      var desc = form.querySelector("[data-maestro-description]");
      var role = form.querySelector("[data-maestro-role]");
      var targetScope = form.querySelector("[data-maestro-target-scope]");
      var targetRoleRow = form.querySelector("[data-maestro-target-role-row]");
      var targetUserRow = form.querySelector("[data-maestro-target-user-row]");
      var targetDetails = form.querySelector(".maestro-target-details");
      var priority = form.querySelector("[data-maestro-priority]");
      var action = form.querySelector("[data-maestro-action]");
      function setSelectValue(el, value) {
        if (!el || value == null || value === "") return;
        Array.prototype.some.call(el.options, function (o) {
          if (o.value === String(value)) {
            el.value = String(value);
            return true;
          }
          return false;
        });
      }
      function applyDataset(ds, overwrite) {
        if (!ds) return;
        if (amount && (overwrite || !amount.value))
          amount.value = ds.amount || amount.value || "0";
        if (help) {
          var unit = ds.unit || "days";
          var unitLabel =
            unit === "minutes"
              ? "minutos"
              : unit === "hours"
                ? "horas"
                : "dias";
          help.textContent =
            (ds.amountLabel || "Prazo") + " da rotina em " + unitLabel + ".";
        }
        if (name) name.value = ds.name || name.value || "";
        if (title) title.value = ds.title || title.value || "";
        if (desc) desc.value = ds.description || desc.value || "";
        if (priority && (overwrite || !priority.value))
          priority.value = ds.priority || priority.value || "50";
        if (action) setSelectValue(action, ds.action || "create_task");
        if (role) setSelectValue(role, ds.role || role.value);
      }
      function applySelected(overwrite) {
        if (!trigger) return;
        var opt = trigger.options[trigger.selectedIndex];
        if (opt) applyDataset(opt.dataset, overwrite);
      }
      function filterTriggers() {
        if (!moduleSelect || !trigger) return;
        var mod = moduleSelect.value;
        var first = "";
        Array.prototype.forEach.call(trigger.options, function (opt) {
          var matches = !mod || opt.dataset.module === mod;
          opt.hidden = !matches;
          opt.disabled = !matches;
          if (matches && !first) first = opt.value;
        });
        var current = trigger.options[trigger.selectedIndex];
        if (!current || current.disabled) {
          trigger.value = first;
        }
        applySelected(true);
      }
      function syncTargetRows() {
        var scope = targetScope ? targetScope.value : "role";
        if (targetRoleRow) targetRoleRow.hidden = scope !== "role";
        if (targetUserRow) targetUserRow.hidden = scope !== "user";
        if (targetDetails) targetDetails.hidden = scope === "clinic";
      }
      if (moduleSelect) {
        moduleSelect.addEventListener("change", filterTriggers);
      }
      if (trigger) {
        trigger.addEventListener("change", function () {
          applySelected(true);
        });
      }
      if (targetScope) {
        targetScope.addEventListener("change", syncTargetRows);
      }
      filterTriggers();
      syncTargetRows();
      applySelected(false);
    });
  }
  ready(function () {
    initLeadCreateLookup();
    initLeadConvertLookup();
    initMaestro();
  });
})();
(function () {
  function closeOtherClinicMenus(current) {
    document
      .querySelectorAll("details[data-clinic-actions-menu][open]")
      .forEach(function (menu) {
        if (menu !== current) menu.removeAttribute("open");
      });
  }
  document.addEventListener(
    "toggle",
    function (ev) {
      var menu = ev.target;
      if (
        menu &&
        menu.matches &&
        menu.matches("details[data-clinic-actions-menu]") &&
        menu.open
      ) {
        closeOtherClinicMenus(menu);
      }
    },
    true,
  );
  document.addEventListener("click", function (ev) {
    if (
      ev.target.closest &&
      ev.target.closest("details[data-clinic-actions-menu]")
    )
      return;
    closeOtherClinicMenus(null);
  });
  document.addEventListener("keydown", function (ev) {
    if (ev.key === "Escape") closeOtherClinicMenus(null);
  });
})();
(function () {
  function pad(n) {
    return String(n).padStart(2, "0");
  }
  function addMinutes(localValue, minutes) {
    var d = new Date(localValue);
    if (isNaN(d.getTime())) return localValue;
    d.setMinutes(d.getMinutes() + minutes);
    return (
      d.getFullYear() +
      "-" +
      pad(d.getMonth() + 1) +
      "-" +
      pad(d.getDate()) +
      "T" +
      pad(d.getHours()) +
      ":" +
      pad(d.getMinutes())
    );
  }
  function roundSlotFromClick(canvas, ev) {
    var rect = canvas.getBoundingClientRect();
    var y = Math.max(0, Math.min(rect.height, ev.clientY - rect.top));
    var slotMinutes = parseInt(canvas.dataset.slotMinutes || "15", 10) || 15;
    var slotHeight =
      parseFloat(
        getComputedStyle(canvas).getPropertyValue("--agenda-slot-height"),
      ) || 34;
    var slot = Math.floor(y / slotHeight);
    var start = canvas.dataset.workStart || "08:00";
    var parts = start.split(":");
    var h = parseInt(parts[0] || "8", 10),
      m = parseInt(parts[1] || "0", 10);
    var total = h * 60 + m + slot * slotMinutes;
    var day = canvas.dataset.day || new Date().toISOString().slice(0, 10);
    return day + "T" + pad(Math.floor(total / 60)) + ":" + pad(total % 60);
  }
  function procedureDuration(select) {
    var duration = 30;
    if (select && select.selectedOptions && select.selectedOptions[0]) {
      var d = parseInt(select.selectedOptions[0].dataset.duration || "0", 10);
      if (d > 0) duration = d;
    }
    return duration;
  }
  function syncAppointmentEnd(form) {
    if (!form) return;
    var start = form.querySelector("[data-appointment-start]");
    var end = form.querySelector("[data-appointment-end]");
    var proc = form.querySelector("[data-procedure-select]");
    if (!start || !end || !start.value) return;
    end.value = addMinutes(start.value, procedureDuration(proc));
  }
  document.addEventListener("click", function (ev) {
    if (
      ev.target.closest &&
      ev.target.closest(
        ".agenda-day-event,.agenda-day-event-block,a,button,input,select,textarea,summary,details",
      )
    )
      return;
    var canvas = ev.target.closest && ev.target.closest("[data-agenda-canvas]");
    if (canvas) {
      var base = canvas.dataset.agendaCreateUrl || "";
      if (!base) return;
      var startValue = roundSlotFromClick(canvas, ev);
      var sep = base.indexOf("?") >= 0 ? "&" : "?";
      window.location.href =
        base + sep + "start=" + encodeURIComponent(startValue);
    }
  });
  document.addEventListener("change", function (ev) {
    var proc =
      ev.target.closest &&
      ev.target.closest(
        "[data-appointment-create-form] [data-procedure-select]",
      );
    if (proc)
      syncAppointmentEnd(proc.closest("[data-appointment-create-form]"));
    var start =
      ev.target.closest &&
      ev.target.closest(
        "[data-appointment-create-form] [data-appointment-start]",
      );
    if (start)
      syncAppointmentEnd(start.closest("[data-appointment-create-form]"));
  });
})();
(function () {
  function promoteFixedLayers() {
    var layers = document.querySelectorAll("[data-ds-fixed-layer]");
    layers.forEach(function (layer) {
      if (layer.parentNode !== document.body) {
        document.body.appendChild(layer);
      }
    });
  }
  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", promoteFixedLayers);
  else promoteFixedLayers();
})();
function initPasswordToggle(root = document) {
  root
    .querySelectorAll(
      'input[type="password"][data-password-toggle],input[type="text"][data-password-toggle]',
    )
    .forEach((input) => {
      if (input.dataset.passwordToggleReady) return;
      input.dataset.passwordToggleReady = "1";
      let wrap = input.closest(".password-field");
      if (!wrap) {
        wrap = document.createElement("div");
        wrap.className = "password-field";
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
      }
      let btn = wrap.querySelector("[data-password-toggle-button]");
      if (!btn) {
        btn = document.createElement("button");
        btn.type = "button";
        btn.className = "password-toggle";
        btn.setAttribute("data-password-toggle-button", "1");
        btn.setAttribute("aria-label", "Mostrar senha");
        btn.innerHTML =
          '<span class="material-symbols-rounded">visibility</span>';
        wrap.appendChild(btn);
      }
      btn.addEventListener("click", () => {
        const show = input.type === "password";
        input.type = show ? "text" : "password";
        btn.setAttribute(
          "aria-label",
          show ? "Ocultar senha" : "Mostrar senha",
        );
        btn.innerHTML =
          '<span class="material-symbols-rounded">' +
          (show ? "visibility_off" : "visibility") +
          "</span>";
        input.focus();
      });
    });
}
(function () {
  const boot = () => initPasswordToggle(document);
  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  else boot();
})();
(function () {
  function details() {
    return Array.prototype.slice.call(
      document.querySelectorAll(".pagehead-controls--actions details[open]"),
    );
  }
  function closePageheadActions(current) {
    details().forEach(function (menu) {
      if (menu !== current) menu.removeAttribute("open");
    });
  }
  document.addEventListener(
    "toggle",
    function (ev) {
      var menu = ev.target;
      if (!menu || !menu.matches || !menu.matches(".pagehead-controls--actions details"))
        return;
      if (menu.open) {
        closePageheadActions(menu);
        setTimeout(function () {
          var form = menu.querySelector("form");
          if (!form) return;
          var first = form.querySelector(
            'select:not([disabled]),input:not([type="hidden"]):not([disabled]):not([readonly]),textarea:not([disabled]),button:not([disabled])',
          );
          if (first) {
            try {
              first.focus({ preventScroll: true });
            } catch (_) {
              try {
                first.focus();
              } catch (__) {}
            }
          }
        }, 40);
      }
    },
    true,
  );
  document.addEventListener("click", function (ev) {
    var close = ev.target.closest && ev.target.closest("[data-close-panel]");
    if (close) {
      var menu = close.closest("details[open]");
      if (menu) {
        menu.removeAttribute("open");
        ev.preventDefault();
        return;
      }
      var form = close.closest && close.closest("form");
      if (form) {
        try {
          form.reset();
        } catch (_) {}
        var first = form.querySelector(
          'select:not([disabled]),input:not([type="hidden"]):not([disabled]):not([readonly]),textarea:not([disabled])',
        );
        if (first) {
          try {
            first.focus({ preventScroll: true });
          } catch (_) {
            try {
              first.focus();
            } catch (__) {}
          }
        }
        ev.preventDefault();
        return;
      }
      return;
    }
    if (ev.target.closest && ev.target.closest(".pagehead-controls--actions details"))
      return;
    closePageheadActions(null);
  });
  document.addEventListener("keydown", function (ev) {
    if (ev.key === "Escape") closePageheadActions(null);
  });
})();

(function () {
  function arr(list) {
    return Array.prototype.slice.call(list || []);
  }
  function find(root, selector) {
    var out = [];
    try {
      if (root && root.matches && root.matches(selector)) out.push(root);
    } catch (_) {}
    try {
      if (root && root.querySelectorAll)
        out = out.concat(arr(root.querySelectorAll(selector)));
    } catch (_) {}
    return out;
  }
  function excluded(el) {
    return (
      !el ||
      !el.closest ||
      !!el.closest(
        ".pagehead,.pagehead-controls--actions,.top,.topbar,.cmdbar,.agenda-floating-kpis,.ds-fixed-agenda-kpis,.floating-clock,.top-account,.onboarding-tip-card",
      )
    );
  }
  function mark(el, kind) {
    if (!el || !el.setAttribute || excluded(el) || !el.closest("main")) return;
    el.setAttribute("data-ds-card-kind", kind);
  }
  function classify(root) {
    root = root || document;
    var containers = find(
      root,
      "main :is(.kpis,.stats-grid,.lead-overview,.patient-directory-overview,.task-overview,.admin-overview-kpis,.manager-kpis,.manager-operational-kpis,.finance-kpis,.procedure-kpis,.procedure-kpis-refined,.notice-kpis,.notice-gmail-kpis,.finance-mini-grid,.manager-mini-grid,.finance-drawer-metrics,.patient-profile-summary-cards,.patient-profile-footer-cards,.patient-summary-cards,.patient-mini-grid,.global-telemetry-grid,.global-performance-charts):not(.agenda-floating-kpis)",
    );
    containers.forEach(function (box) {
      if (excluded(box)) return;
      box.setAttribute("data-ds-kpi-scope", "1");
      arr(box.children).forEach(function (child) {
        mark(child, "compact");
      });
      var panel = box.closest(
        "main > .card, main > .widebox, main .dashboard-card, main .panel-card, main .reception-board, main .global-pill-section, main .finance-drawer-admin-card",
      );
      if (panel && !excluded(panel))
        panel.setAttribute("data-ds-card-kind", "card");
    });
    find(
      root,
      "main :is(.stat-card,.mini-stat,.ds-kpi,.notice-kpi,.cash-drawer-metric,.finance-drawer-metric,.manager-stat,.global-compact-summary,.audit-mini,.kpi-card,.patient-kpi-card,.patient-stat-pill):not(.agenda-floating-card)",
    ).forEach(function (el) {
      mark(el, "compact");
    });
    find(
      root,
      'main :is([data-ds-card-kind="card"],[data-ds-card-kind="compact"],.kpis,.stats-grid,.notice-kpis,.finance-kpis,.procedure-kpis,.manager-kpis,.lead-overview,.patient-directory-overview,.task-overview) :is(.pill,.status,.badge-soft,.global-compact-pill,.notice-direction-pill,.notice-recipient-pill,.lead-chip,.task-chip,.role-badge)',
    ).forEach(function (el) {
      mark(el, "pill");
    });
  }
  function boot() {
    classify(document);
    try {
      new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
          arr(m.addedNodes).forEach(function (n) {
            if (n && n.nodeType === 1) classify(n);
          });
        });
      }).observe(document.body, { childList: true, subtree: true });
    } catch (_) {}
  }
  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  else boot();
})();

(function () {
  function arr(x) {
    return Array.prototype.slice.call(x || []);
  }
  function isExcluded(el) {
    return (
      !el ||
      !el.closest ||
      !!el.closest(
        ".pagehead,.pagehead-controls--actions,.top,.cmdbar,.agenda-floating-kpis,.onboarding-tip-card",
      )
    );
  }
  function hintFor(form) {
    if (form.matches(".patient-search-bar")) return "";
    if (form.matches(".lead-search")) return "";
    if (form.matches(".task-search"))
      return "Filtre tarefas por paciente, responsável, descrição ou data.";
    if (form.matches(".doc-model-search"))
      return "Localize documentos por conteúdo, paciente, título ou código.";
    return "Digite parte da informação e confirme para refinar a lista.";
  }
  function labelFor(form) {
    if (form.matches(".patient-search-bar")) return "Busca de pacientes";
    if (form.matches(".lead-search")) return "Busca de interessados";
    if (form.matches(".task-search")) return "Busca de tarefas";
    if (form.matches(".doc-model-search")) return "Busca de documentos";
    return "Busca";
  }
  function enhance(root) {
    root = root || document;
    var forms = [];
    try {
      forms = arr(
        root.querySelectorAll
          ? root.querySelectorAll(
              'main form:has(input[type="search"]), main .doc-search-primary form',
            )
          : [],
      );
    } catch (_) {
      try {
        forms = arr(document.querySelectorAll("main form")).filter(
          function (f) {
            return !!f.querySelector('input[type="search"]');
          },
        );
      } catch (__) {
        forms = [];
      }
    }
    if (root && root.matches) {
      try {
        if (root.matches("main form, main form *") && root.closest) {
          var rf = root.matches("form") ? root : root.closest("form");
          if (rf && rf.querySelector('input[type="search"]')) forms.push(rf);
        }
      } catch (_) {}
    }
    forms.forEach(function (form) {
      if (isExcluded(form)) return;
      var input = form.querySelector('input[type="search"]');
      if (!input) return;
      form.setAttribute("data-ds-search-scope", "1");
      if (!form.getAttribute("role")) form.setAttribute("role", "search");
      if (!form.getAttribute("aria-label"))
        form.setAttribute("aria-label", labelFor(form));
      input.setAttribute("data-ds-search-input", "1");
      if (!input.getAttribute("aria-label"))
        input.setAttribute(
          "aria-label",
          input.getAttribute("placeholder") || labelFor(form),
        );
      if (
        (input.hasAttribute("list") ||
          input.hasAttribute("data-patient-document-suggest") ||
          input.hasAttribute("data-counterparty-document-suggest")) &&
        !input.getAttribute("data-ds-lookup")
      ) {
        input.setAttribute("data-ds-lookup", "true");
      }
      var hint = hintFor(form);
      if (
        hint &&
        !form.querySelector(".ds-search-help") &&
        (form.matches(
          ".patient-search-bar,.lead-search,.task-search,.doc-model-search",
        ) ||
          form.closest(".doc-search-primary"))
      ) {
        var small = document.createElement("small");
        small.className = "ds-search-help";
        small.textContent = hint;
        form.appendChild(small);
      }
    });
  }
  function boot() {
    enhance(document);
    try {
      new MutationObserver(function (ms) {
        ms.forEach(function (m) {
          arr(m.addedNodes).forEach(function (n) {
            if (n && n.nodeType === 1) enhance(n);
          });
        });
      }).observe(document.body, { childList: true, subtree: true });
    } catch (_) {}
  }
  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  else boot();
})();

(function () {
  function agendaFloatingLayers() {
    return Array.prototype.slice.call(
      document.querySelectorAll(
        'body[data-route="appointments"] .agenda-floating-kpis, body[data-route="appointments"] .ds-fixed-agenda-kpis',
      ),
    );
  }
  function syncAgendaFloatingWidth() {
    agendaFloatingLayers().forEach(function (layer) {
      var cards = Array.prototype.slice.call(
        layer.querySelectorAll(
          '.agenda-floating-card, [class*="agenda-floating-card-"]',
        ),
      );
      if (!cards.length) return;
      var previousLayerWidth = layer.style.width;
      var previousVar = layer.style.getPropertyValue("--agenda-floating-w");
      var previousCardWidths = cards.map(function (card) {
        return card.style.width;
      });
      layer.style.removeProperty("--agenda-floating-w");
      layer.style.width = "max-content";
      cards.forEach(function (card) {
        card.style.width = "max-content";
      });
      var max = 0;
      cards.forEach(function (card) {
        var rect = card.getBoundingClientRect();
        max = Math.max(
          max,
          Math.ceil(rect.width),
          Math.ceil(card.scrollWidth || 0),
        );
      });
      cards.forEach(function (card, index) {
        card.style.width = previousCardWidths[index] || "";
      });
      layer.style.width = previousLayerWidth || "";
      if (previousVar)
        layer.style.setProperty("--agenda-floating-w", previousVar);
      var viewport = Math.max(
        260,
        window.innerWidth || document.documentElement.clientWidth || 360,
      );
      var cap = Math.min(viewport - 6, viewport <= 720 ? 324 : 360);
      var width = Math.max(0, Math.min(Math.max(max, 0), cap));
      if (width > 0) {
        var px = Math.ceil(width) + "px";
        layer.style.setProperty("--agenda-floating-w", px);
        try {
          document.body.style.setProperty("--pt-floating-reference-w", px);
        } catch (_) {}
        try {
          document.documentElement.style.setProperty(
            "--pt-floating-reference-w",
            px,
          );
        } catch (_) {}
      }
    });
  }
  var scheduled = false;
  function requestSyncAgendaFloatingWidth() {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(function () {
      scheduled = false;
      syncAgendaFloatingWidth();
    });
  }
  if (document.readyState === "loading")
    document.addEventListener(
      "DOMContentLoaded",
      requestSyncAgendaFloatingWidth,
      { once: true },
    );
  else requestSyncAgendaFloatingWidth();
  window.addEventListener("resize", requestSyncAgendaFloatingWidth, {
    passive: true,
  });
  if (document.fonts && document.fonts.ready)
    document.fonts.ready
      .then(requestSyncAgendaFloatingWidth)
      .catch(function () {});
  document.addEventListener("prontoo:ui-ready", requestSyncAgendaFloatingWidth);
})();

(function () {
  function bars() {
    return Array.prototype.slice.call(document.querySelectorAll(".cmdbar"));
  }
  function activeItem(bar) {
    return (
      bar &&
      bar.querySelector(
        '.cmd.active,.cmd.is-active,.cmd[aria-current="page"],.cmd[aria-current="true"],.cmd[aria-current]',
      )
    );
  }
  function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
  }
  function reducedMotion() {
    try {
      return (
        window.matchMedia &&
        window.matchMedia("(prefers-reduced-motion: reduce)").matches
      );
    } catch (_) {
      return true;
    }
  }
  function centerCmdbar(bar, instant) {
    var active = activeItem(bar);
    if (!bar || !active) return;
    var overflow = (bar.scrollWidth || 0) - (bar.clientWidth || 0);
    if (overflow <= 2) {
      try {
        bar.scrollLeft = 0;
      } catch (_) {}
      return;
    }
    var target = active.offsetLeft - (bar.clientWidth - active.offsetWidth) / 2;
    target = clamp(Math.round(target), 0, Math.max(0, overflow));
    try {
      bar.scrollTo({
        left: target,
        behavior: instant || reducedMotion() ? "auto" : "smooth",
      });
    } catch (_) {
      try {
        bar.scrollLeft = target;
      } catch (__) {}
    }
  }
  var scheduled = false;
  function sync(instant) {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        scheduled = false;
        bars().forEach(function (bar) {
          centerCmdbar(bar, instant);
        });
      });
    });
  }
  function boot() {
    sync(true);
  }
  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  else boot();
  window.addEventListener(
    "resize",
    function () {
      sync(true);
    },
    { passive: true },
  );
  window.addEventListener(
    "orientationchange",
    function () {
      setTimeout(function () {
        sync(true);
      }, 180);
    },
    { passive: true },
  );
  if (document.fonts && document.fonts.ready)
    document.fonts.ready
      .then(function () {
        sync(true);
      })
      .catch(function () {});
  document.addEventListener("prontoo:ui-ready", function () {
    sync(true);
  });
  try {
    new MutationObserver(function () {
      sync(true);
    }).observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ["class", "aria-current"],
    });
  } catch (_) {}
})();

(function () {
  function arr(x) {
    return Array.prototype.slice.call(x || []);
  }
  function find(root, sel) {
    try {
      return arr((root || document).querySelectorAll(sel));
    } catch (_) {
      return [];
    }
  }
  function skip(el) {
    return (
      !el ||
      !el.closest ||
      !!el.closest(
        ".top,.cmdbar,.rolebar,.pagehead,.pagehead-controls--actions,.agenda-floating-kpis,.context-floating-pending-kpis,.floating-clock,.onboarding-tip-card",
      )
    );
  }
  function set(el, name, value) {
    if (el && el.setAttribute && !el.hasAttribute(name))
      el.setAttribute(name, value);
  }
  function classify(root) {
    root = root || document;
    find(
      root,
      "main :is(.card,.widebox,.dashboard-card,.panel-card,.reception-board,.global-pill-section,.finance-drawer-admin-card,.finance-form,.finance-list,.finance-accounts-panel,.finance-report-card,.task-list-card,.notice-list-card,.documents-card,.document-card,.patient-card,.settings-card,.maestro-card)",
    ).forEach(function (el) {
      if (!skip(el)) set(el, "data-ds-surface", "card");
    });
    find(
      root,
      "main :is(.patient-card-row,.guardian-row,.lead-list-item,.task-card,.procedure-card,.doc-history-row,.doc-model-card,.document-issued-row,.finance-row,.notice-row,.collaborator-card-row,.audit-row,.maestro-rule-row,.clinic-choice,.credential-choice)",
    ).forEach(function (el) {
      if (!skip(el)) set(el, "data-ds-row-kind", "surface");
    });
    find(
      root,
      'main :is([data-ds-card-kind="compact"],[data-ds-kpi-scope="1"] > *,.stat-card,.mini-stat,.ds-kpi,.notice-kpi,.cash-drawer-metric,.finance-drawer-metric,.manager-stat,.global-compact-summary,.audit-mini,.kpi-card,.patient-kpi-card,.patient-stat-pill,.finance-balance-card) > :is(.material-symbols-rounded,.material-symbol,.material-icons):first-child',
    ).forEach(function (el) {
      if (!skip(el)) set(el, "data-ds-icon-chip", "1");
    });
    find(
      root,
      'main :is([data-ds-row-kind="surface"],.patient-card-row,.guardian-row,.lead-list-item,.task-card,.procedure-card,.doc-history-row,.doc-model-card,.document-issued-row,.finance-row,.notice-row,.collaborator-card-row,.audit-row,.maestro-rule-row) > :is(.material-symbols-rounded,.material-symbol,.material-icons):first-child',
    ).forEach(function (el) {
      if (!skip(el)) set(el, "data-ds-icon-chip", "1");
    });
  }
  function boot() {
    classify(document);
    try {
      new MutationObserver(function (ms) {
        ms.forEach(function (m) {
          arr(m.addedNodes).forEach(function (n) {
            if (n && n.nodeType === 1) classify(n);
          });
        });
      }).observe(document.body, { childList: true, subtree: true });
    } catch (_) {}
  }
  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  else boot();
})();

(function () {
  function arr(x) {
    return Array.prototype.slice.call(x || []);
  }
  function isEditable(el) {
    return !!(
      el &&
      el.closest &&
      el.closest(
        'input,select,textarea,button,summary,[contenteditable="true"]',
      )
    );
  }
  function initWeekScroll(root) {
    arr((root || document).querySelectorAll(".agenda-week-view")).forEach(
      function (view) {
        if (view.dataset.weekScrollReady === "1") return;
        view.dataset.weekScrollReady = "1";
        view.dataset.weekScrollReady = "1";
        var state = null;
        var raf = 0;
        var nextLeft = 0;
        function maxScroll() {
          return Math.max(0, (view.scrollWidth || 0) - (view.clientWidth || 0));
        }
        function clamp(v) {
          return Math.max(0, Math.min(maxScroll(), v));
        }
        function apply() {
          raf = 0;
          view.scrollLeft = clamp(nextLeft);
        }
        function schedule(left) {
          nextLeft = clamp(left);
          if (!raf) raf = window.requestAnimationFrame(apply);
        }
        view.addEventListener(
          "pointerdown",
          function (ev) {
            if (ev.button !== undefined && ev.button !== 0) return;
            if (isEditable(ev.target)) return;
            if (maxScroll() <= 2) return;
            state = {
              id: ev.pointerId,
              startX: ev.clientX,
              startY: ev.clientY,
              lastX: ev.clientX,
              lastY: ev.clientY,
              dragging: false,
              horizontal: false,
              moved: 0,
            };
          },
          { passive: true },
        );
        view.addEventListener(
          "pointermove",
          function (ev) {
            if (!state || state.id !== ev.pointerId) return;
            var totalDx = ev.clientX - state.startX;
            var totalDy = ev.clientY - state.startY;
            var ax = Math.abs(totalDx),
              ay = Math.abs(totalDy);
            if (!state.dragging) {
              if (ax < 6 && ay < 6) return;
              state.dragging = true;
              state.horizontal = ax > ay;
              if (state.horizontal) {
                try {
                  view.setPointerCapture(ev.pointerId);
                } catch (_) {}
                view.classList.add("is-touch-scrolling", "is-drag-scrolling");
              } else {
                state = null;
                return;
              }
            }
            if (state.horizontal) {
              try {
                ev.preventDefault();
              } catch (_) {}
              var stepX = ev.clientX - state.lastX;
              state.moved += Math.abs(stepX);
              state.lastX = ev.clientX;
              state.lastY = ev.clientY;
              schedule(view.scrollLeft - stepX);
              if (state.moved > 5)
                view.dataset.suppressClickUntil = String(Date.now() + 420);
            }
          },
          { passive: false },
        );
        function finish(ev) {
          if (state && state.id === ev.pointerId) {
            if (state.dragging && state.horizontal && state.moved > 5)
              view.dataset.suppressClickUntil = String(Date.now() + 420);
            try {
              view.releasePointerCapture(ev.pointerId);
            } catch (_) {}
          }
          state = null;
          if (raf) {
            window.cancelAnimationFrame(raf);
            raf = 0;
          }
          window.setTimeout(function () {
            view.classList.remove("is-touch-scrolling", "is-drag-scrolling");
          }, 70);
        }
        view.addEventListener("pointerup", finish, { passive: true });
        view.addEventListener("pointercancel", finish, { passive: true });
        view.addEventListener(
          "lostpointercapture",
          function () {
            state = null;
            if (raf) {
              window.cancelAnimationFrame(raf);
              raf = 0;
            }
            window.setTimeout(function () {
              view.classList.remove("is-touch-scrolling", "is-drag-scrolling");
            }, 70);
          },
          { passive: true },
        );
        view.addEventListener(
          "click",
          function (ev) {
            var until = Number(view.dataset.suppressClickUntil || 0);
            if (until && Date.now() < until) {
              ev.preventDefault();
              ev.stopPropagation();
              if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();
            }
          },
          true,
        );
      },
    );
  }
  function boot() {
    initWeekScroll(document);
    try {
      new MutationObserver(function (ms) {
        ms.forEach(function (m) {
          arr(m.addedNodes).forEach(function (n) {
            if (n && n.nodeType === 1) initWeekScroll(n);
          });
        });
      }).observe(document.body, { childList: true, subtree: true });
    } catch (_) {}
  }
  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  else boot();
})();

(function () {
  function initActivityInfinite(root) {
    root = root || document;
    var list =
      root.querySelector && root.querySelector("[data-activity-results]");
    var sentinel =
      root.querySelector && root.querySelector("[data-activity-sentinel]");
    if (!list || !sentinel || list.dataset.activityInfiniteReady) return;
    list.dataset.activityInfiniteReady = "1";
    var loading = false;
    function loadMore() {
      if (loading || list.dataset.activityDone === "1") return;
      var url = list.dataset.activityUrl || "";
      if (!url) return;
      var offset = parseInt(list.dataset.activityNextOffset || "10", 10) || 10;
      loading = true;
      sentinel.classList.add("is-loading");
      var sep = url.indexOf("?") >= 0 ? "&" : "?";
      fetch(url + sep + "offset=" + encodeURIComponent(offset), {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (data && data.ok && data.html) {
            var wrap = document.createElement("div");
            wrap.className = "activity-timeline-page";
            wrap.innerHTML = data.html;
            list.appendChild(wrap);
            list.dataset.activityNextOffset = String(
              data.next_offset || offset + 10,
            );
            if (!data.has_more) {
              list.dataset.activityDone = "1";
              sentinel.remove();
            }
          } else {
            list.dataset.activityDone = "1";
            sentinel.remove();
          }
        })
        .catch(function () {
          list.dataset.activityDone = "1";
          sentinel.remove();
        })
        .finally(function () {
          loading = false;
          if (sentinel) sentinel.classList.remove("is-loading");
        });
    }
    if ("IntersectionObserver" in window) {
      var io = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) loadMore();
          });
        },
        { rootMargin: "240px" },
      );
      io.observe(sentinel);
    } else {
      window.addEventListener(
        "scroll",
        function () {
          if (sentinel.getBoundingClientRect().top < window.innerHeight + 240)
            loadMore();
        },
        { passive: true },
      );
    }
  }
  if (document.readyState === "loading")
    document.addEventListener(
      "DOMContentLoaded",
      function () {
        initActivityInfinite(document);
      },
      { once: true },
    );
  else initActivityInfinite(document);
})();

(function () {
  function openDialog(dialog) {
    if (!dialog) return;
    try {
      if (typeof dialog.showModal === "function") dialog.showModal();
      else dialog.setAttribute("open", "open");
    } catch (_) {
      dialog.setAttribute("open", "open");
    }
    var input = dialog.querySelector('input[type="date"]');
    if (input)
      setTimeout(function () {
        try {
          input.focus();
        } catch (_) {}
      }, 40);
  }
  function closeDialog(dialog) {
    if (!dialog) return;
    try {
      if (typeof dialog.close === "function") dialog.close();
      else dialog.removeAttribute("open");
    } catch (_) {
      dialog.removeAttribute("open");
    }
  }
  document.addEventListener("click", function (ev) {
    var open =
      ev.target.closest && ev.target.closest("[data-open-activity-date]");
    if (open) {
      ev.preventDefault();
      openDialog(document.querySelector("[data-activity-date-dialog]"));
      return;
    }
    var close =
      ev.target.closest && ev.target.closest("[data-close-activity-date]");
    if (close) {
      ev.preventDefault();
      closeDialog(close.closest("dialog"));
    }
  });
  document.addEventListener("keydown", function (ev) {
    if (ev.key === "Escape")
      document
        .querySelectorAll("[data-activity-date-dialog][open]")
        .forEach(closeDialog);
  });
})();

(function () {
  function closestInteractive(el) {
    return (
      el &&
      el.closest &&
      el.closest(
        "a,button,input,select,textarea,label,details,[data-agenda-row-actions]",
      )
    );
  }
  function setRowOpen(row, open) {
    if (!row) return;
    var expanded = row.querySelector("[data-agenda-row-expanded]");
    var summary = row.querySelector("[data-agenda-row-summary]");
    row.classList.toggle("is-open", !!open);
    if (expanded) expanded.hidden = !open;
    if (summary) summary.setAttribute("aria-expanded", open ? "true" : "false");
  }
  document.addEventListener(
    "click",
    function (ev) {
      var actionMenu =
        ev.target && ev.target.closest
          ? ev.target.closest("[data-agenda-row-actions]")
          : null;
      if (actionMenu) {
        ev.stopPropagation();
        return;
      }
      var toggle =
        ev.target && ev.target.closest
          ? ev.target.closest("[data-agenda-row-toggle]")
          : null;
      if (toggle) {
        var row = toggle.closest("[data-agenda-row]");
        if (row) {
          ev.preventDefault();
          ev.stopPropagation();
          setRowOpen(row, !row.classList.contains("is-open"));
        }
        return;
      }
      var summary =
        ev.target && ev.target.closest
          ? ev.target.closest("[data-agenda-row-summary]")
          : null;
      if (summary && !closestInteractive(ev.target)) {
        var row = summary.closest("[data-agenda-row]");
        if (row) {
          ev.preventDefault();
          setRowOpen(row, !row.classList.contains("is-open"));
        }
      }
    },
    false,
  );
  document.addEventListener(
    "keydown",
    function (ev) {
      var summary =
        ev.target && ev.target.closest
          ? ev.target.closest("[data-agenda-row-summary]")
          : null;
      if (!summary || closestInteractive(ev.target)) return;
      if (ev.key !== "Enter" && ev.key !== " ") return;
      var row = summary.closest("[data-agenda-row]");
      if (row) {
        ev.preventDefault();
        setRowOpen(row, !row.classList.contains("is-open"));
      }
    },
    false,
  );
})();

(function () {
  if (window.__prontooAgendaActionsModalReady) return;
  window.__prontooAgendaActionsModalReady = true;
  function firstFocusable(root) {
    return (
      root &&
      root.querySelector(
        'button:not([disabled]),[href],input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])',
      )
    );
  }
  function allModals() {
    return Array.prototype.slice.call(
      document.querySelectorAll("dialog[data-agenda-actions-modal]"),
    );
  }
  function closeModal(modal) {
    if (!modal) return;
    try {
      if (typeof modal.close === "function" && modal.open) modal.close();
      else modal.removeAttribute("open");
    } catch (_) {
      modal.removeAttribute("open");
    }
    modal.classList.remove("is-open", "is-fallback-open");
  }
  function closeOthers(current) {
    allModals().forEach(function (modal) {
      if (modal !== current) closeModal(modal);
    });
  }
  function positionModal(modal, trigger) {
    if (!modal || !trigger) return;
    var rect = trigger.getBoundingClientRect();
    var width = Math.min(280, Math.max(220, window.innerWidth - 24));
    var currentWidth = modal.offsetWidth || width;
    var currentHeight = modal.offsetHeight || 180;
    var left = rect.right - currentWidth;
    left = Math.max(12, Math.min(left, window.innerWidth - currentWidth - 12));
    var top = rect.bottom + 8;
    if (top + currentHeight > window.innerHeight - 12) {
      top = Math.max(12, rect.top - currentHeight - 8);
    }
    modal.style.setProperty("--agenda-actions-left", left + "px");
    modal.style.setProperty("--agenda-actions-top", top + "px");
  }
  function openModal(modal, trigger) {
    if (!modal || !trigger) return false;
    if (modal.parentNode !== document.body) {
      try {
        document.body.appendChild(modal);
      } catch (_) {}
    }
    closeOthers(modal);
    modal.dataset.returnFocusId = trigger.id || "";
    modal.__agendaActionsTrigger = trigger;
    positionModal(modal, trigger);
    try {
      if (typeof modal.showModal === "function") {
        if (!modal.open) modal.showModal();
      } else {
        modal.setAttribute("open", "open");
        modal.classList.add("is-fallback-open");
      }
    } catch (_) {
      modal.setAttribute("open", "open");
      modal.classList.add("is-fallback-open");
    }
    modal.classList.add("is-open");
    requestAnimationFrame(function () {
      positionModal(modal, trigger);
      var target = firstFocusable(modal);
      if (target) {
        try {
          target.focus({ preventScroll: true });
        } catch (_) {
          try {
            target.focus();
          } catch (__) {}
        }
      }
    });
    return true;
  }
  document.addEventListener(
    "click",
    function (ev) {
      var el = ev.target && ev.target.closest ? ev.target : null;
      if (!el) return;
      var openBtn = el.closest("[data-agenda-actions-open]");
      if (openBtn) {
        var id =
          openBtn.getAttribute("data-agenda-actions-open") ||
          openBtn.getAttribute("aria-controls") ||
          "";
        var modal = id ? document.getElementById(id) : null;
        if (openModal(modal, openBtn)) {
          ev.preventDefault();
          ev.stopPropagation();
        }
        return;
      }
      var closeBtn = el.closest("[data-agenda-actions-close]");
      if (closeBtn) {
        closeModal(closeBtn.closest("dialog[data-agenda-actions-modal]"));
        ev.preventDefault();
        ev.stopPropagation();
        return;
      }
      var modal =
        el.matches && el.matches("dialog[data-agenda-actions-modal]")
          ? el
          : null;
      if (modal) {
        var card = modal.querySelector(".agenda-actions-modal-card");
        if (card && !card.contains(el)) {
          closeModal(modal);
          ev.preventDefault();
          ev.stopPropagation();
        }
      }
    },
    false,
  );
  document.addEventListener(
    "keydown",
    function (ev) {
      if (ev.key !== "Escape") return;
      var modal = document.querySelector(
        "dialog[data-agenda-actions-modal][open]",
      );
      if (modal) {
        closeModal(modal);
        ev.preventDefault();
      }
    },
    false,
  );
  window.addEventListener(
    "resize",
    function () {
      allModals().forEach(function (modal) {
        if (modal.open && modal.__agendaActionsTrigger)
          positionModal(modal, modal.__agendaActionsTrigger);
      });
    },
    { passive: true },
  );
  window.addEventListener(
    "scroll",
    function () {
      allModals().forEach(function (modal) {
        if (modal.open && modal.__agendaActionsTrigger)
          positionModal(modal, modal.__agendaActionsTrigger);
      });
    },
    { passive: true },
  );
  document.addEventListener(
    "close",
    function (ev) {
      var modal = ev.target;
      if (
        !modal ||
        !modal.matches ||
        !modal.matches("dialog[data-agenda-actions-modal]")
      )
        return;
      modal.classList.remove("is-open", "is-fallback-open");
      var id = modal.dataset.returnFocusId || "";
      if (id) {
        var target = document.getElementById(id);
        if (target) {
          try {
            target.focus({ preventScroll: true });
          } catch (_) {}
        }
      }
    },
    true,
  );
})();

(function () {
  "use strict";
  if (window.__prontooFloatingEndFadeReady) return;
  window.__prontooFloatingEndFadeReady = true;

  var mq = null;
  var scheduled = false;
  var booted = false;
  var lastPageTop = 0;
  var lastTopByElement = typeof WeakMap !== "undefined" ? new WeakMap() : null;
  var bound = typeof WeakSet !== "undefined" ? new WeakSet() : null;
  var scanTimer = 0;

  try {
    mq = window.matchMedia ? window.matchMedia("(max-width: 720px)") : null;
  } catch (_) {
    mq = null;
  }

  function isMobile() {
    try {
      return mq
        ? mq.matches
        : (window.innerWidth || document.documentElement.clientWidth || 1024) <=
            720;
    } catch (_) {
      return false;
    }
  }
  function arr(list) {
    return Array.prototype.slice.call(list || []);
  }
  function visibleFloatingSelectors() {
    return [
      'body[data-route="appointments"] .agenda-floating-kpis',
      'body[data-route="appointments"] .ds-fixed-agenda-kpis',
      'body[data-route="appointments"] > .agenda-floating-kpis',
      'body[data-route="appointments"] > .ds-fixed-agenda-kpis',
      'body:not([data-route="appointments"]) .context-floating-pending-kpis',
      ".lead-floating-pending",
      ".floating-clock.context-floating-clock-card",
      ".floating-clock[data-floating-clock]",
      "[data-floating-clock].floating-clock",
    ].join(",");
  }
  function unique(list) {
    var out = [],
      seen = [];
    list.forEach(function (el) {
      if (el && seen.indexOf(el) < 0) {
        seen.push(el);
        out.push(el);
      }
    });
    return out;
  }
  function floatingLayers() {
    try {
      return unique(arr(document.querySelectorAll(visibleFloatingSelectors())));
    } catch (_) {
      return [];
    }
  }
  function setHidden(hidden) {
    try {
      if (!isMobile()) hidden = false;
      if (hidden)
        document.body.setAttribute("data-floating-scroll-hidden", "1");
      else document.body.removeAttribute("data-floating-scroll-hidden");
    } catch (_) {}
  }
  function cleanupDragArtifacts() {
    try {
      document
        .querySelectorAll(".pt-floating-drag-handle")
        .forEach(function (el) {
          el.remove();
        });
    } catch (_) {}
    floatingLayers().forEach(function (el) {
      try {
        delete el.dataset.floatingSharedDraggable;
        delete el.dataset.floatingSharedDragging;
        delete el.dataset.floatingMobileSharedPosition;
        delete el.dataset.floatingEfficientDrag;
        delete el.dataset.floatingSharedDragReady;
        el.removeAttribute("title");
        el.style.removeProperty("top");
        el.style.removeProperty("bottom");
        el.dataset.floatingFixedFade = "1";
      } catch (_) {}
    });
    try {
      document.body.style.removeProperty("--pt-floating-mobile-anchor-top");
      document.documentElement.style.removeProperty(
        "--pt-floating-mobile-anchor-top",
      );
      document.body.removeAttribute("data-floating-shared-dragging");
    } catch (_) {}
  }

  function isPage(el) {
    return (
      !el ||
      el === window ||
      el === document ||
      el === document.body ||
      el === document.documentElement ||
      el === document.scrollingElement
    );
  }
  function pageRoot() {
    return (
      document.scrollingElement || document.documentElement || document.body
    );
  }
  function topOf(el) {
    try {
      if (isPage(el))
        return Math.max(
          0,
          window.scrollY || window.pageYOffset || pageRoot().scrollTop || 0,
        );
      return Math.max(0, Number(el.scrollTop || 0));
    } catch (_) {
      return 0;
    }
  }
  function clientHeightOf(el) {
    try {
      if (isPage(el))
        return Math.max(
          1,
          (window.visualViewport && window.visualViewport.height) ||
            window.innerHeight ||
            document.documentElement.clientHeight ||
            1,
        );
      return Math.max(1, Number(el.clientHeight || 1));
    } catch (_) {
      return 1;
    }
  }
  function scrollHeightOf(el) {
    try {
      if (isPage(el)) {
        var r = pageRoot();
        return Math.max(
          Number((r && r.scrollHeight) || 0),
          Number(
            (document.documentElement &&
              document.documentElement.scrollHeight) ||
              0,
          ),
          Number((document.body && document.body.scrollHeight) || 0),
        );
      }
      return Math.max(0, Number(el.scrollHeight || 0));
    } catch (_) {
      return 0;
    }
  }
  function maxOf(el) {
    return Math.max(0, scrollHeightOf(el) - clientHeightOf(el));
  }
  function canScroll(el) {
    return maxOf(el) > 8;
  }
  function isScrollable(el) {
    try {
      if (!el || el.nodeType !== 1) return false;
      if (!canScroll(el)) return false;
      var cs = window.getComputedStyle ? window.getComputedStyle(el) : null;
      var y = cs ? String(cs.overflowY || "") : "";
      return /(auto|scroll|overlay)/i.test(y) || el === pageRoot();
    } catch (_) {
      return false;
    }
  }
  function closestScrollable(target) {
    try {
      var el =
        target && target.nodeType === 1
          ? target
          : target && target.parentElement
            ? target.parentElement
            : null;
      while (el && el !== document.body && el !== document.documentElement) {
        if (isScrollable(el)) return el;
        el = el.parentElement;
      }
    } catch (_) {}
    return pageRoot();
  }
  function rootFromEventTarget(target) {
    if (isPage(target)) return pageRoot();
    return isScrollable(target) ? target : closestScrollable(target);
  }
  function lastTop(root) {
    if (isPage(root)) return lastPageTop;
    try {
      return lastTopByElement
        ? Number(lastTopByElement.get(root) || 0)
        : Number(root.dataset.prontooFloatingLastTop || 0);
    } catch (_) {
      return 0;
    }
  }
  function storeTop(root, value) {
    if (isPage(root)) {
      lastPageTop = value;
      return;
    }
    try {
      if (lastTopByElement) lastTopByElement.set(root, value);
      else root.dataset.prontooFloatingLastTop = String(value);
    } catch (_) {}
  }
  function atEnd(root, top) {
    var max = maxOf(root);
    if (max <= 8) return false;
    return top >= Math.max(0, max - 4);
  }
  function handleScrollRoot(root, reason) {
    if (!isMobile()) {
      setHidden(false);
      return;
    }
    root = root || pageRoot();
    if (!canScroll(root)) {
      setHidden(false);
      storeTop(root, topOf(root));
      return;
    }
    var top = topOf(root);
    var prev = lastTop(root);
    var delta = top - prev;
    if (delta < -1) {
      setHidden(false);
    } else if (delta > 0 && atEnd(root, top)) {
      setHidden(true);
    }
    storeTop(root, top);
  }
  function onScroll(ev) {
    var root = rootFromEventTarget(ev && ev.target ? ev.target : null);
    handleScrollRoot(root, "scroll");
  }
  function resetState() {
    cleanupDragArtifacts();
    if (!isMobile()) {
      setHidden(false);
      return;
    }
    lastPageTop = topOf(pageRoot());
    bindKnownScrollables();
    setHidden(false);
  }
  function bindOne(el) {
    if (!el || el === window || el === document) return;
    if (bound) {
      if (bound.has(el)) return;
      bound.add(el);
    } else {
      if (el.dataset && el.dataset.prontooFloatingScrollBound === "1") return;
      try {
        el.dataset.prontooFloatingScrollBound = "1";
      } catch (_) {}
    }
    try {
      el.addEventListener("scroll", onScroll, { passive: true });
    } catch (_) {}
  }
  function bindKnownScrollables() {
    if (!isMobile()) return;
    try {
      bindOne(pageRoot());
    } catch (_) {}
    try {
      arr(
        document.querySelectorAll(
          "main, main *, .app-shell, .page-shell, .content-shell",
        ),
      ).forEach(function (el) {
        if (isScrollable(el)) bindOne(el);
      });
    } catch (_) {}
  }
  function scheduleReset() {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(function () {
      scheduled = false;
      resetState();
    });
  }
  function start() {
    if (booted) return;
    booted = true;
    cleanupDragArtifacts();
    bindKnownScrollables();
    setHidden(false);
    try {
      window.addEventListener("scroll", onScroll, { passive: true });
    } catch (_) {}
    try {
      document.addEventListener("scroll", onScroll, true);
    } catch (_) {}
    try {
      window.addEventListener("resize", scheduleReset, { passive: true });
    } catch (_) {}
    try {
      window.addEventListener(
        "orientationchange",
        function () {
          setTimeout(scheduleReset, 180);
        },
        { passive: true },
      );
    } catch (_) {}
    try {
      if (window.visualViewport) {
        window.visualViewport.addEventListener("resize", scheduleReset, {
          passive: true,
        });
      }
    } catch (_) {}
    try {
      if (mq && mq.addEventListener)
        mq.addEventListener("change", scheduleReset);
    } catch (_) {}
    document.addEventListener("prontoo:ui-ready", scheduleReset);
    try {
      new MutationObserver(function () {
        scheduleReset();
      }).observe(document.body, { childList: true, subtree: true });
    } catch (_) {}
    scanTimer = window.setInterval(function () {
      if (isMobile()) bindKnownScrollables();
    }, 1600);
  }
  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", start, { once: true });
  else start();
})();
