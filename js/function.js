// JavaScript Document
//opacity
var t,t2,obj,op;
function appear(x)   // x - конечное значение прозрачности
{
	op = (obj.style.opacity)?parseFloat(obj.style.opacity):parseInt(obj.style.filter)/100;
	
	if(op < x) {
		clearTimeout(t2);
		op += 0.05;
		obj.style.opacity = op;
		obj.style.filter='alpha(opacity='+op*100+')';
		t=setTimeout('appear('+x+')',20);
	}
}
function disappear(x) {
	op = (obj.style.opacity)?parseFloat(obj.style.opacity):parseInt(obj.style.filter)/100;
	
	if(op > x) {
		clearTimeout(t);
		op -= 0.05;
		obj.style.opacity = op;
		obj.style.filter='alpha(opacity='+op*100+')';
		t2=setTimeout('disappear('+x+')',20);
	}
}
//ajax loader
function ajaxLoader(url,id) {
  if (document.getElementById) {
    var x = (window.ActiveXObject) ? new ActiveXObject("Microsoft.XMLHTTP") : new XMLHttpRequest();
  }
  if (x) {
    x.onreadystatechange = function() {
      if (x.readyState == 4 && x.status == 200) {
        el = document.getElementById(id);
        el.innerHTML = x.responseText;
      }
    }
    x.open("GET", url, true);
    x.send(null);
  }
}
//butoons
function comment() {
	document.comment.submit();
}
//box
var overlay = document.createElement("div");
overlay.id = "overlay";
overlay.onclick = hideOverlay;
document.body.appendChild(overlay);
function hideOverlay() {
	curlImage = null;
	hide(id("overlay"));
	hide(id("gallerey"));
}
function showOverlay() {
	var over = id("overlay");
	over.style.height = pageHeight() + "px";
	over.style.width = pageWidth() + "px";
	fadeIn(over,50,10);
}
function createMessage(title) {
  var container = document.createElement('div')
  container.innerHTML = '<div class="my-message"> \
    <div class="my-message-title">'+title+'</div> \
    <div class="my-message-body">'+ajaxLoader('edit.php','my-message-body')+'</div> \
    <input class="my-message-ok" type="button" value="OK"/> \
  </div>'
  return container.firstChild
}
function positionMessage(elem) {
  elem.style.position = 'absolute'
  var scroll = document.documentElement.scrollTop || document.body.scrollTop
  elem.style.top = scroll + 200 + 'px'
  elem.style.left = Math.floor(document.body.clientWidth/2) - 150 + 'px'
}
function addCloseOnClick(messageElem) {
  var input = messageElem.getElementsByTagName('INPUT')[0]
  input.onclick = function() {
    messageElem.parentNode.removeChild(messageElem)
  }
}
function setupMessageButton(title) {
  var messageElem = createMessage(title)
  positionMessage(messageElem)
  addCloseOnClick(messageElem)
  document.body.appendChild(messageElem)
}

// Обработчик кнопок покупки из виджета магазина на profile.php.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.shop-buy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var productId = btn.getAttribute('data-product');
      var csrf = btn.getAttribute('data-csrf');
      var original = btn.textContent;

      btn.disabled = true;
      btn.textContent = 'Добавляем…';

      var formData = new FormData();
      formData.append('action', 'add_to_cart');
      formData.append('product_id', productId);
      formData.append('csrf_token', csrf);

      fetch('modules/shop.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().then(function (data) {
            return { ok: response.ok, data: data };
          });
        })
        .then(function (result) {
          if (result.ok && result.data.status === 'ok') {
            btn.textContent = '✓ В корзине';
            setTimeout(function () {
              btn.textContent = original;
              btn.disabled = false;
            }, 1500);
          } else {
            alert(result.data.error || 'Не удалось добавить товар в корзину.');
            btn.textContent = original;
            btn.disabled = false;
          }
        })
        .catch(function () {
          alert('Ошибка соединения с сервером.');
          btn.textContent = original;
          btn.disabled = false;
        });
    });
  });
});


// Отзывы на оплаченные товары: форма со звёздами и счётчиком символов.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-review-form]').forEach(function (form) {
    var textarea = form.querySelector('textarea[name="review"]');
    var counter = form.querySelector('.review_char_counter');

    // Живой счётчик оставшихся символов.
    if (textarea && counter) {
      var max = parseInt(counter.getAttribute('data-max'), 10) || 1000;
      var updateCounter = function () {
        var left = max - textarea.value.length;
        counter.textContent = left >= 0 ? ('Осталось символов: ' + left) : ('Превышение на ' + (-left));
        counter.classList.toggle('is_over', left < 0);
      };
      textarea.addEventListener('input', updateCounter);
      updateCounter();
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var button = form.querySelector('button[type="submit"]');
      var message = form.querySelector('.review_form_message');
      if (button) { button.disabled = true; button.textContent = 'Сохранение…'; }
      var data = new FormData(form);
      data.append('action', 'add_review');
      fetch('modules/shop.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().then(function (json) { return {ok: response.ok, json: json}; });
        })
        .then(function (result) {
          if (!result.ok || result.json.status !== 'ok') {
            throw new Error(result.json.error || 'Не удалось сохранить отзыв.');
          }
          if (message) message.textContent = result.json.message || 'Отзыв сохранён.';
          setTimeout(function () { window.location.reload(); }, 500);
        })
        .catch(function (error) {
          if (message) message.textContent = error.message;
          if (button) { button.disabled = false; button.textContent = 'Оставить отзыв'; }
        });
    });
  });
});

// Модальное окно «Все отзывы на товар»: загрузка по AJAX и отрисовка.
document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('reviews_modal');
  if (!modal) return;

  var titleEl = document.getElementById('reviews_modal_title');
  var subEl = modal.querySelector('.reviews_modal_sub');
  var listEl = modal.querySelector('.reviews_modal_list');
  var avgValueEl = modal.querySelector('.reviews_avg b');
  var avgStarsEl = modal.querySelector('.reviews_avg .stars_top');
  var avgCountEl = modal.querySelector('.reviews_avg small');
  var distEl = modal.querySelector('.reviews_distribution');
  var currentProductId = null;

  function closeModal() {
    modal.hidden = true;
    document.body.classList.remove('reviews_modal_open');
    currentProductId = null;
  }

  function openModal(productId, productName) {
    currentProductId = productId;
    if (titleEl) titleEl.textContent = productName ? ('Отзывы на «' + productName + '»') : 'Отзывы';
    if (subEl) subEl.textContent = 'Загрузка…';
    if (listEl) listEl.innerHTML = '<div class="reviews_loading">Загружаем отзывы…</div>';
    if (distEl) distEl.innerHTML = '';
    if (avgValueEl) avgValueEl.textContent = '—';
    if (avgStarsEl) avgStarsEl.style.width = '0%';
    if (avgCountEl) avgCountEl.textContent = '';

    modal.hidden = false;
    document.body.classList.add('reviews_modal_open');

    var formData = new FormData();
    formData.append('action', 'get_reviews');
    formData.append('product_id', productId);
    formData.append('csrf_token', window.shopCsrfToken || '');

    fetch('modules/shop.php', { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (response) {
        return response.json().then(function (json) { return { ok: response.ok, json: json }; });
      })
      .then(function (result) {
        // Окно могли уже закрыть или открыть для другого товара — игнорируем устаревший ответ.
        if (modal.hidden || String(currentProductId) !== String(productId)) return;
        if (!result.ok || result.json.error) {
          throw new Error(result.json.error || 'Не удалось загрузить отзывы.');
        }
        render(result.json);
      })
      .catch(function (error) {
        if (modal.hidden) return;
        if (listEl) listEl.innerHTML = '<div class="reviews_error">' + escapeHtml(error.message) + '</div>';
        if (subEl) subEl.textContent = '';
      });
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(text == null ? '' : text)));
    return div.innerHTML;
  }

  function starsMarkup(rating) {
    var pct = Math.max(0, Math.min(100, Math.round((Number(rating) / 5) * 100)));
    return '<span class="stars_static" aria-hidden="true">' +
      '<span class="stars_base">★★★★★</span>' +
      '<span class="stars_top" style="width:' + pct + '%">★★★★★</span></span>';
  }

  function pluralReviews(count) {
    if (count === 1) return 'отзыв';
    if (count < 5) return 'отзыва';
    return 'отзывов';
  }

  function render(data) {
    var items = data.items || [];
    var count = Number(data.review_count) || 0;
    var avg = Number(data.avg_rating) || 0;
    var distribution = data.distribution || {};

    if (subEl) subEl.textContent = count > 0 ? (avg.toFixed(1).replace('.', ',') + ' из 5 · ' + count + ' ' + pluralReviews(count)) : 'Пока нет отзывов';
    if (avgValueEl) avgValueEl.textContent = count > 0 ? avg.toFixed(1).replace('.', ',') : '—';
    if (avgStarsEl) avgStarsEl.style.width = Math.round((avg / 5) * 100) + '%';
    if (avgCountEl) avgCountEl.textContent = count > 0 ? (count + ' ' + pluralReviews(count)) : '';

    // Распределение оценок по звёздам.
    if (distEl) {
      distEl.innerHTML = '';
      for (var star = 5; star >= 1; star--) {
        var value = Number(distribution[star]) || 0;
        var row = document.createElement('div');
        row.className = 'reviews_dist_row';
        row.innerHTML =
          '<span class="reviews_dist_star">' + star + '★</span>' +
          '<span class="reviews_dist_bar"><span style="width:' + (count > 0 ? Math.round((value / count) * 100) : 0) + '%"></span></span>' +
          '<span class="reviews_dist_num">' + value + '</span>';
        distEl.appendChild(row);
      }
    }

    if (!listEl) return;
    if (!items.length) {
      listEl.innerHTML = '<div class="reviews_empty">На этот товар ещё никто не оставил отзыв.</div>';
      return;
    }

    listEl.innerHTML = items.map(function (item) {
      var photo = item.buyer_photo ? escapeHtml(item.buyer_photo) : 'images/nophoto.jpg';
      var name = escapeHtml(item.buyer_name);
      var date = escapeHtml(item.created_at_human);
      var text = item.review ? '<p class="review_text">' + escapeHtml(item.review).replace(/\n/g, '<br>') + '</p>' : '';
      return '<article class="review_item">' +
        '<img class="review_avatar" src="' + photo + '" alt="" loading="lazy" />' +
        '<div class="review_body">' +
          '<div class="review_head"><strong>' + name + '</strong><time class="review_date">' + date + '</time></div>' +
          '<div class="review_stars_row">' + starsMarkup(item.rating) + '</div>' +
          text +
        '</div>' +
      '</article>';
    }).join('');
  }

  // Открытие окна по клику на рейтинг товара / кнопку «Все отзывы».
  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-reviews-for]');
    if (!trigger) return;
    event.preventDefault();
    openModal(trigger.getAttribute('data-reviews-for'), trigger.getAttribute('data-product-name') || '');
  });

  modal.querySelectorAll('[data-reviews-close]').forEach(function (el) {
    el.addEventListener('click', closeModal);
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.hidden) closeModal();
  });
});
