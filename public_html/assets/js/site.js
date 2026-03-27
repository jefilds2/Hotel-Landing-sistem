document.addEventListener("DOMContentLoaded", () => {
  // Altere apenas estes 2 valores para atualizar a faixa exibida em toda a landing.
  const CONFIG_TARIFAS = {
    minimo: 60,
    maximo: 80
  };

  const formatarMoedaBr = (valor) =>
    new Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
      minimumFractionDigits: 2
    }).format(valor);

  const faixaFormatada = `${formatarMoedaBr(CONFIG_TARIFAS.minimo)} até ${formatarMoedaBr(CONFIG_TARIFAS.maximo)}`;
  document.querySelectorAll("[data-faixa-valor]").forEach((el) => {
    el.textContent = faixaFormatada;
  });

  const inicializarCarrossel = ({
    containerSelector,
    slideSelector,
    dotSelector,
    prevSelector,
    nextSelector,
    intervalo = 4500
  }) => {
    const container = document.querySelector(containerSelector);
    if (!container) return;

    const slides = Array.from(container.querySelectorAll(slideSelector));
    const dots = Array.from(container.querySelectorAll(dotSelector));
    const botaoAnterior = container.querySelector(prevSelector);
    const botaoProximo = container.querySelector(nextSelector);
    if (!slides.length) return;

    let indiceAtual = 0;
    let timerAuto = null;

    const ativarSlide = (novoIndice) => {
      indiceAtual = (novoIndice + slides.length) % slides.length;
      slides.forEach((slide, indice) => {
        slide.classList.toggle("is-active", indice === indiceAtual);
      });
      dots.forEach((dot, indice) => {
        dot.classList.toggle("is-active", indice === indiceAtual);
      });
    };

    const iniciarAuto = () => {
      if (slides.length <= 1 || timerAuto) return;
      timerAuto = window.setInterval(() => {
        ativarSlide(indiceAtual + 1);
      }, intervalo);
    };

    const pausarAuto = () => {
      if (!timerAuto) return;
      window.clearInterval(timerAuto);
      timerAuto = null;
    };

    botaoAnterior?.addEventListener("click", () => {
      ativarSlide(indiceAtual - 1);
    });

    botaoProximo?.addEventListener("click", () => {
      ativarSlide(indiceAtual + 1);
    });

    dots.forEach((dot, indice) => {
      dot.addEventListener("click", () => {
        ativarSlide(indice);
      });
    });

    container.addEventListener("mouseenter", pausarAuto);
    container.addEventListener("mouseleave", iniciarAuto);
    container.addEventListener("focusin", pausarAuto);
    container.addEventListener("focusout", (evento) => {
      if (evento.relatedTarget && container.contains(evento.relatedTarget)) return;
      iniciarAuto();
    });

    ativarSlide(0);
    iniciarAuto();
  };

  inicializarCarrossel({
    containerSelector: "[data-cafe-carousel]",
    slideSelector: ".cafe-slide",
    dotSelector: ".cafe-dot",
    prevSelector: "[data-cafe-prev]",
    nextSelector: "[data-cafe-next]",
    intervalo: 4500
  });

  const inicializarCarrosselAmbiente = () => {
    const container = document.querySelector("[data-ambiente-multi]");
    if (!container) return;

    const track = container.querySelector("[data-ambiente-track]");
    const cards = Array.from(container.querySelectorAll(".ambiente-card"));
    const prev = container.querySelector("[data-ambiente-multi-prev]");
    const next = container.querySelector("[data-ambiente-multi-next]");
    const dotsWrap = container.querySelector("[data-ambiente-multi-dots]");
    if (!track || !cards.length) return;

    let paginaAtual = 0;
    let paginas = 1;
    let porPagina = 1;
    let dots = [];
    let timerAuto = null;

    const obterPorPagina = () => {
      if (window.matchMedia("(min-width: 1024px)").matches) return 3;
      if (window.matchMedia("(min-width: 760px)").matches) return 2;
      return 1;
    };

    const atualizarBotoes = () => {
      if (prev) prev.disabled = paginaAtual <= 0;
      if (next) next.disabled = paginaAtual >= paginas - 1;
    };

    const atualizarDots = () => {
      dots.forEach((dot, i) => {
        dot.classList.toggle("is-active", i === paginaAtual);
      });
    };

    const aplicarTransform = () => {
      const viewport = container.querySelector(".ambiente-viewport");
      const larguraPagina = viewport ? viewport.clientWidth : container.clientWidth;
      const deslocamento = paginaAtual * larguraPagina;
      track.style.transform = `translateX(-${deslocamento}px)`;
      atualizarBotoes();
      atualizarDots();
    };

    const iniciarAuto = () => {
      if (paginas <= 1 || timerAuto) return;
      timerAuto = window.setInterval(() => {
        paginaAtual = paginaAtual >= paginas - 1 ? 0 : paginaAtual + 1;
        aplicarTransform();
      }, 5200);
    };

    const pausarAuto = () => {
      if (!timerAuto) return;
      window.clearInterval(timerAuto);
      timerAuto = null;
    };

    const construirDots = () => {
      if (!dotsWrap) return;
      dotsWrap.innerHTML = "";
      dots = [];
      for (let i = 0; i < paginas; i += 1) {
        const dot = document.createElement("button");
        dot.type = "button";
        dot.className = `ambiente-dot${i === paginaAtual ? " is-active" : ""}`;
        dot.setAttribute("aria-label", `Ver página ${i + 1} do ambiente`);
        dot.addEventListener("click", () => {
          paginaAtual = i;
          aplicarTransform();
        });
        dotsWrap.appendChild(dot);
        dots.push(dot);
      }
    };

    const recalcular = () => {
      porPagina = obterPorPagina();
      paginas = Math.max(1, Math.ceil(cards.length / porPagina));
      if (paginaAtual > paginas - 1) paginaAtual = paginas - 1;
      construirDots();
      aplicarTransform();
    };

    prev?.addEventListener("click", () => {
      if (paginaAtual <= 0) return;
      paginaAtual -= 1;
      aplicarTransform();
    });

    next?.addEventListener("click", () => {
      if (paginaAtual >= paginas - 1) return;
      paginaAtual += 1;
      aplicarTransform();
    });

    container.addEventListener("mouseenter", pausarAuto);
    container.addEventListener("mouseleave", iniciarAuto);
    container.addEventListener("focusin", pausarAuto);
    container.addEventListener("focusout", (evento) => {
      if (evento.relatedTarget && container.contains(evento.relatedTarget)) return;
      iniciarAuto();
    });

    window.addEventListener("resize", recalcular);
    recalcular();
    iniciarAuto();
  };

  inicializarCarrosselAmbiente();

  const elementos = document.querySelectorAll("[data-reveal]");

  const observador = new IntersectionObserver(
    (entradas) => {
      entradas.forEach((entrada) => {
        if (entrada.isIntersecting) {
          entrada.target.classList.add("show");
          observador.unobserve(entrada.target);
        }
      });
    },
    { threshold: 0.15 }
  );

  elementos.forEach((el) => observador.observe(el));
});
