const modal = `<div class="modal fade" tabindex="-1" role="dialog" id="error-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Внимание!</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p id="message-text"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>`;

if (!$('#error-modal').length) {
  $('body').append(modal);
}

$(document).on('click.add', '.action-button', (e) => {
  const button = $(e.target)
    , branchId = +button.attr('id').slice(3)
    , action = (button.hasClass('branches-button')) ? 'add' : 'remove';

  // при отправке меняем текст кнопки (Sending request...) ставим спиннер и делаем кнопку неактивной
  button.removeClass('active-button').addClass('request-button');
  console.log('Отправляем запрос');

  $.post('/lib/controller.php?to_queue=1', {
    branch_id: branchId,
    action: action,
  }).done((data) => {
      console.log(data, 'done');

      // пришел ответ от сервера, меняем текст кнопки (In process...)
      button.removeClass('request-button').addClass('process-button');

      /*
      * после нажатия кнопки ждем 5 секунд до перезагрузки страницы;
      * если есть другие нажатые кнопки, то время ожидания увеличивается на 5 сек для каждой
      * время подобрано эмпирически
      * лучше использовать сокеты
      * todo add socket
      */
      let timeout = 5000 + ($('.process-button:not(.process-button-finished)').length - 1) * 5 * 1000;

      console.log('Устанавливаем таймаут:' + timeout);

      setTimeout(() => {
          button.addClass('process-button-finished');

          if (!$('.request-button').length && !$('.process-button:not(.process-button-finished)').length) {
            console.log('Перезагружаем страницу');
            document.location.reload();
          } else {
            console.log('Не перезагружаем страницу, т.к. еще есть выполняемые задачи');
          }
        }
        , timeout);

    }
  ).fail((e) => {
    console.error(e, 'fail');
  });
});
