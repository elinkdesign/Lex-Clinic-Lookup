@asset::/forms/js/jquery.inputmask.min.js

<div class="row">
	<div class="col-xs-12">
		<?php 
		    $notify = new \sa\utilities\notification();
		    $notify->showNotifications();
		?>
	</div>
</div>

<?php if($has_duplicates) { ?>
	<div class="row">
		<div class="col-xs-12">
			<div class="notifcationcontainer">
				<div class="alert alert-block alert-danger"> 
					<div class="row"> 
						<div class="pull-left" style="padding-left: 10px; font-size: 25px;"> 
							<strong> <i class="fa fa-exclamation-triangle"></i> Error </strong> 
						</div> 
						<div class="pull-left" style="padding: 10px 0px 0px 25px;"> 
							There are existing records with the data provided.
							<br /><br />
							<strong><h2>Long List Matches</h2></strong>
							<table class="table table-bordered">
								<thead>
									<tr>
										<th>Legacy ID</th>
										<th>NPI</th>
										<th>Line Description</th>
									<tr/>
								</thead>
								<tbody>
									<?php foreach ($longListMatches as $match) { ?>
										<tr>
											<td><?= $match['Provider Legacy ID'] ?></td>
											<td><?= $match['Provider NPI'] ?></td>
											<td><?= $match['Line Description'] ?></td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
							<br /><br />
							<strong><h2>Short List Matches</h2></strong>
							<table class="table table-bordered">
								<tr>
									<th>Legacy ID</th>
									<th>NPI</th>
									<th>Line Description</th>
								<tr/>
								<?php foreach ($shortListMatches as $match) { ?>
									<tr>
										<td><?= $match['Provider Legacy ID'] ?></td>
										<td><?= $match['Provider NPI'] ?></td>
										<td><?= $match['Line Description'] ?></td>
									</tr>
								<?php } ?>
							</table>
							<button id="existing-records-delete-btn" class="btn btn-primary"><i class="fa fa-trash"></i>&nbsp;Delete Existing Records &amp; Re-Submit</button>
						</div> 
					</div>
				</div>
			</div>
		</div>
	</div>
<?php } ?>

<div id="lookup">
	<div class="modal fade" id="lookupModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	  	<div class="modal-dialog" role="document">
		    <div class="modal-content">
			    <div class="modal-header">
			    	<h5 class="modal-title" id="exampleModalLabel">Lookup Tool</h5>
			        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
			          <span aria-hidden="true">&times;</span>
			        </button>
		      	</div>
			    
			    <div class="modal-body">
			    	<div class="text-center">
		    			Search for Legacy ID or NPI below and click Lookup to find existing records
			    	</div><br/>
			    	<div class="row">
		    			<div class="col-xs-4">
		    				<select v-model="searchType" class="form-control">
		    					<option value="legacy_id">Legacy ID</option>
		    					<option value="npi">NPI</option>
		    				</select>
		    			</div>
			    		<div class="col-xs-5">
			    			<input class="form-control" type="text" v-model="query" Placeholder="Search Here">
		    			</div>
		    			<div class="col-xs-2">
		    				<button class="btn btn-primary" @click="lookup" :disabled="query.length == 0" >Lookup</button>
		    			</div>
			    	</div>
			    	<br />
			    	<br />
			    	<div class="row">
			    		<div class="col-xs-12" v-if="match != null && match != false">
			    			<h3>Match:</h3>
			    			<span>Legacy ID: </span><span>{{ match != null ? match['Provider Legacy ID'] : '' }}</span><br /><br />
			    			<span>NPI: </span><span>{{ match != null ? match['Provider NPI'] : '' }}</span><br /><br />
			    			<span>Line Description: </span> {{ match != null ? match['Line Description'] : '' }}<br /><br />

			    			<div class="text-center">
			    				<button @click="deleteAction" class="btn btn-danger">DELETE</button>
			    			</div>
			    		</div>

			    		<div class="col-xs-12 text-center" v-if="searchActive == true && (match == null || match == false)">
			    			No Matches.
			    		</div>
			    	</div>
			    </div>
		    	<div class="modal-footer">
			        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
			    </div>
		    </div>
	  	</div>
	</div>
</div>


<div class="row">
	<div class="col-xs-12 text-right">
		<button class="btn btn-primary" data-toggle="modal" data-target="#lookupModal">Lookup</button>
	</div>
</div>

<form id="cpdr-form" method="POST" action="<?= \sa\utilities\url::make('cpdr_list_form_post'); ?>">
	<input type="hidden" id="delete-existing-records" name="delete-existing-records" />
	<div class="row">
		<div class="col-xs-12">
			<div class="form-group">
				<label for="legacy-id">Legacy ID</label>
				<input name="legacy_id" id="legacy-id" type="text" class="form-control" maxlength="10" value="<?= $legacy_id; ?>" />
			</div>
			<div class="form-group">
				<label for="npi">NPI</label>
				<input name="npi" id="npi" type="text" class="form-control" maxlength="10" value="<?= $npi; ?>" />
			</div>
			<div class="form-group">
				<label for="line-description">Line Description</label>
				<input name="line_description" id="line-description" type="text" class="form-control" maxlength="50" value="<?= $line_description; ?>" />
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-xs-12 text-center">
			<button class="btn-lg btn-primary" type="submit">Submit</button>
		</div>
	</div>
</form>

<script>
	$(document).ready(function() {
		$('#legacy-id').inputmask('***');
		
		$('#npi').inputmask('9999999999');

		$('#line-description').keyup(function() {
			var replaced = $(this).val().replace(/[^A-Z ^a-z ^0-9 ^ ^-]/gm, '');

			$(this).val(replaced);
		});
	});

	$(document).ready(function() {
		$('#existing-records-delete-btn').click(function() {
			$('#delete-existing-records').val('1');
			
			$('#cpdr-form').submit();
		});

		var vueInstance = new Vue({
			el: '#lookup',
			data: function() {
				return {
					match: null,
					searchType: 'npi',
					query: '',
					searchActive: false
				};
			},
			mounted: function() {
				$('#lookupModal').on('hide.bs.modal', function () {
					if(this.match != null) {
						this.match = null;
						this.query = '';
						this.searchActive = false;
						this.searchType = 'npi';
					}
				}.bind(this));
			},
			methods: {
				lookup: function() {
					var data = {
						query: this.query,
						searchType: this.searchType
					};

					this.searchActive = true;

					modRequest.request('cpdr.lookup', null, data, function(response) {
						this.match = response.data.match;
					}.bind(this), 
					function(error) {
						console.log(error);
					});
				},
				deleteAction: function() {
					var data = {
						legacy_id: this.match['Provider Legacy ID'],
						npi: this.match['Provider NPI'],
						line_description: this.match['Line Description']
					};

					var response = confirm('Are you sure you wish to delete ' + this.match['Provider Legacy ID'] + '?');

					if(!response) {
						return;
					}

					modRequest.request('cpdr.delete', null, data, function() {
						this.match = null;
						this.query = '';
						this.searchType = 'npi';
						this.searchActive = false;

						$('#lookupModal').modal('hide');

						alert('Item was successfully deleted.');
					}.bind(this), function() {}.bind(this));
				}
			}
		});
	});


</script>